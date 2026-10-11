use serde::Serialize;
use sqlx::FromRow;

// PHP urlencode: spaces are '+', literal '+' is %2B, '~' is %7E.
pub fn encode(s: &str) -> String {
    s.as_bytes()
        .iter()
        .map(|b| match b {
            b' ' => "+".into(),
            b'A'..=b'Z' | b'a'..=b'z' | b'0'..=b'9' | b'-' | b'_' | b'.' => {
                (*b as char).to_string()
            }
            _ => format!("%{b:02X}"),
        })
        .collect()
}
// Axum already percent-decodes path captures. Decode '+' BEFORE percent escapes
// using the raw URI instead, so an encoded literal '+' stays a literal '+'.
pub fn decode(s: &str) -> Result<String, &'static str> {
    let bytes = s.as_bytes();
    for (i, b) in bytes.iter().enumerate() {
        if *b == b'%'
            && (i + 2 >= bytes.len()
                || !bytes[i + 1].is_ascii_hexdigit()
                || !bytes[i + 2].is_ascii_hexdigit())
        {
            return Err("Invalid URL encoding");
        }
    }
    percent_encoding::percent_decode_str(&s.replace('+', " "))
        .decode_utf8()
        .map(|s| s.into_owned())
        .map_err(|_| "Invalid UTF-8")
}
pub fn pretty(names: &[String]) -> String {
    match names.len() {
        0 => String::new(),
        1 => names[0].clone(),
        n => format!("{} and {}", names[..n - 1].join(", "), names[n - 1]),
    }
}
#[derive(Clone, Debug, Default, FromRow)]
pub struct Game {
    pub id: String,
    pub live: i64,
    pub short_name: String,
    pub theme_color: String,
    pub thumb_url: String,
    pub header_url: String,
}
impl Game {
    pub fn url(&self) -> String {
        format!("/{}", encode(&self.id))
    }
    pub fn label(&self) -> &str {
        if self.id.len() < 17 {
            &self.id
        } else {
            &self.short_name
        }
    }
    pub fn qualified(&self) -> String {
        if self.short_name.is_empty() {
            self.id.clone()
        } else {
            format!("{} ({})", self.id, self.short_name)
        }
    }
}
#[derive(Clone, Debug, Default, FromRow)]
pub struct Weapon {
    pub id: i64,
    pub name: String,
    pub game_id: String,
    pub kind: String,
    pub image_url: String,
    pub thumb_url: String,
    pub min_attachments: i64,
    pub max_attachments: i64,
}
impl Weapon {
    pub fn url(&self) -> String {
        format!("/{}/{}", encode(&self.game_id), encode(&self.name))
    }
}
#[derive(Clone, Debug, Default, FromRow)]
pub struct Attachment {
    pub id: i64,
    pub loadout_id: i64,
    pub name: String,
    pub slot: String,
    pub image_url: String,
    pub thumb_url: String,
}
#[derive(Clone, Debug, Default, FromRow)]
pub struct Loadout {
    pub id: i64,
    pub weapon_id: i64,
    pub game_id: String,
    pub weapon_name: String,
    pub votes: i64,
    pub author: String,
    #[sqlx(skip)]
    pub attachments: Vec<Attachment>,
}
impl Loadout {
    pub fn url(&self) -> String {
        format!(
            "/{}/{}/{}",
            encode(&self.game_id),
            encode(&self.weapon_name),
            self.id
        )
    }
    pub fn pretty(&self) -> String {
        pretty(
            &self
                .attachments
                .iter()
                .map(|a| a.name.clone())
                .collect::<Vec<_>>(),
        )
    }
}
#[derive(Clone, Debug)]
pub struct Popular {
    pub game: Game,
    pub loadouts: Vec<Loadout>,
}
#[derive(Debug)]
pub struct WeaponGroup {
    pub kind: String,
    pub weapons: Vec<Weapon>,
}
#[derive(Debug)]
pub struct AttachmentGroup {
    pub slot: String,
    pub attachments: Vec<Attachment>,
}
#[derive(Debug, FromRow)]
pub struct Stats {
    pub game: String,
    pub votes: i64,
    pub loadouts: i64,
}
#[derive(Debug, FromRow)]
pub struct Day {
    pub game: String,
    pub date: String,
    pub votes: i64,
}
#[derive(Debug, Serialize)]
pub struct SearchItem {
    pub name: String,
    pub url: String,
    pub snippet: String,
}

#[cfg(test)]
mod tests {
    use super::*;
    #[test]
    fn php_urls() {
        for (raw, encoded) in [
            ("Call of Duty Modern Warfare", "Call+of+Duty+Modern+Warfare"),
            ("A+B / C%~", "A%2BB+%2F+C%25%7E"),
            ("Pokémon & '銃'", "Pok%C3%A9mon+%26+%27%E9%8A%83%27"),
        ] {
            assert_eq!(encode(raw), encoded);
            assert_eq!(decode(encoded).unwrap(), raw);
        }
        assert_eq!(decode("A%20B").unwrap(), "A B");
        assert!(decode("%ZZ").is_err());
        assert!(decode("%FF").is_err());
    }
    #[test]
    fn attachment_metadata() {
        assert_eq!(pretty(&["A".into(), "B".into(), "C".into()]), "A, B and C");
    }
}
