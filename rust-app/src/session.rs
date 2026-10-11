//! Versioned, origin-bound authenticated encryption. No session files or database rows.
use base64::{Engine, engine::general_purpose::URL_SAFE_NO_PAD};
use hmac::{Hmac, Mac};
use rand::RngCore;
use ring::aead::{self, Aad, LessSafeKey, Nonce, UnboundKey};
use serde::{Deserialize, Serialize};
use sha2::Sha256;

pub const MAX_AGE: u64 = 30 * 24 * 3600;
pub fn now() -> u64 {
    std::time::SystemTime::now()
        .duration_since(std::time::UNIX_EPOCH)
        .unwrap_or_default()
        .as_secs()
}
#[derive(Serialize, Deserialize)]
#[serde(deny_unknown_fields)]
pub struct Claims {
    pub id: String,
    pub csrf: String,
    pub email: String,
    pub credential_hash: String,
    pub expires_at: u64,
}
pub struct CookieSessions {
    key: LessSafeKey,
    context: String,
}
impl CookieSessions {
    pub fn new(secret: &[u8], origin: &str) -> Result<Self, &'static str> {
        if secret.len() < 32 {
            return Err("Session encryption requires a secret of at least 32 bytes");
        }
        // Derive a distinct key for this purpose and origin from the existing application key.
        let mut kdf = Hmac::<Sha256>::new_from_slice(secret).map_err(|_| "Invalid session key")?;
        kdf.update(b"game-loadouts/rust/session/v1\0");
        kdf.update(origin.as_bytes());
        let derived = kdf.finalize().into_bytes();
        let key =
            UnboundKey::new(&aead::AES_256_GCM, &derived).map_err(|_| "Invalid session key")?;
        Ok(Self {
            key: LessSafeKey::new(key),
            context: format!("gameloadouts_rust_session/v1/{origin}"),
        })
    }
    pub fn seal(&self, claims: &Claims) -> Result<String, &'static str> {
        let mut nonce = [0u8; 12];
        rand::rngs::OsRng.fill_bytes(&mut nonce);
        let mut data = serde_json::to_vec(claims).map_err(|_| "Invalid session")?;
        self.key
            .seal_in_place_append_tag(
                Nonce::assume_unique_for_key(nonce),
                Aad::from(self.context.as_bytes()),
                &mut data,
            )
            .map_err(|_| "Session encryption failed")?;
        let mut output = nonce.to_vec();
        output.extend(data);
        let token = format!("v1.{}", URL_SAFE_NO_PAD.encode(output));
        if token.len() > 2048 {
            return Err("Session exceeds cookie limit");
        }
        Ok(token)
    }
    pub fn open(&self, token: &str) -> Option<Claims> {
        if token.len() > 2048 {
            return None;
        }
        let mut data = URL_SAFE_NO_PAD.decode(token.strip_prefix("v1.")?).ok()?;
        if data.len() < 28 {
            return None;
        }
        let nonce: [u8; 12] = data[..12].try_into().ok()?;
        let plaintext = self
            .key
            .open_in_place(
                Nonce::assume_unique_for_key(nonce),
                Aad::from(self.context.as_bytes()),
                &mut data[12..],
            )
            .ok()?;
        let claims: Claims = serde_json::from_slice(plaintext).ok()?;
        let hex = |s: &str| s.len() == 64 && s.bytes().all(|c| c.is_ascii_hexdigit());
        let time = now();
        if claims.expires_at <= time
            || claims.expires_at > time.saturating_add(MAX_AGE)
            || !hex(&claims.id)
            || !hex(&claims.csrf)
            || claims.email.len() > 255
            || (claims.email.is_empty() && !claims.credential_hash.is_empty())
            || (!claims.email.is_empty() && !hex(&claims.credential_hash))
        {
            return None;
        }
        Some(claims)
    }
}
#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn encryption_tampering_expiry_and_instance_compatibility() {
        let key = b"synthetic-session-key-32-bytes-long";
        let a = CookieSessions::new(key, "https://rust.example.invalid").unwrap();
        let b = CookieSessions::new(key, "https://rust.example.invalid").unwrap();
        let mut claims = Claims {
            id: "a".repeat(64),
            csrf: "b".repeat(64),
            email: "member@example.invalid".into(),
            credential_hash: "c".repeat(64),
            expires_at: now() + 7200,
        };
        let token = a.seal(&claims).unwrap();
        assert_eq!(b.open(&token).unwrap().email, claims.email);
        assert_ne!(a.seal(&claims).unwrap(), token);
        let bytes = URL_SAFE_NO_PAD
            .decode(token.strip_prefix("v1.").unwrap())
            .unwrap();
        assert!(
            !bytes
                .windows(claims.email.len())
                .any(|w| w == claims.email.as_bytes())
        );
        for index in [0, 12, bytes.len() - 1] {
            let mut changed = bytes.clone();
            changed[index] ^= 1;
            assert!(
                a.open(&format!("v1.{}", URL_SAFE_NO_PAD.encode(changed)))
                    .is_none()
            );
        }
        assert!(
            CookieSessions::new(key, "https://other.example.invalid")
                .unwrap()
                .open(&token)
                .is_none()
        );
        assert!(
            CookieSessions::new(
                b"another-synthetic-key-32-bytes-long",
                "https://rust.example.invalid"
            )
            .unwrap()
            .open(&token)
            .is_none()
        );
        claims.expires_at = now() - 1;
        assert!(a.open(&a.seal(&claims).unwrap()).is_none());
        claims.expires_at = now() + MAX_AGE + 60;
        assert!(a.open(&a.seal(&claims).unwrap()).is_none());
        for invalid in ["", "v1.bad", "v2.bad", &"x".repeat(4096)] {
            assert!(a.open(invalid).is_none());
        }
        assert!(CookieSessions::new(b"short", "https://rust.example.invalid").is_err());
    }
}
