# Build from the repository root. PHP remains available via Dockerfile.php.
FROM rust:1.99-slim-trixie AS build
WORKDIR /build
COPY rust-app/Cargo.toml rust-app/Cargo.lock ./
COPY rust-app/src ./src
COPY rust-app/templates ./templates
COPY rust-app/data ./data
RUN cargo build --locked --release

FROM debian:trixie-slim AS runtime
RUN apt-get update && apt-get install -y --no-install-recommends ca-certificates \
    && rm -rf /var/lib/apt/lists/* \
    && groupadd --gid 10001 app && useradd --uid 10001 --gid app --no-create-home app
WORKDIR /app
COPY --from=build /build/target/release/game-loadouts /app/game-loadouts
COPY public/css /app/public/css
COPY public/js /app/public/js
COPY public/fonts /app/public/fonts
COPY public/font-awesome/css /app/public/font-awesome/css
COPY public/font-awesome/fonts /app/public/font-awesome/fonts
COPY public/img /app/public/img
COPY public/uploads /app/public/uploads
COPY public/favicon.ico /app/public/favicon.ico
ENV PORT=8080 ASSET_DIR=/app/public RUST_LOG=game_loadouts=info
USER 10001:10001
EXPOSE 8080
ENTRYPOINT ["/app/game-loadouts"]
