FROM nginx:1.27-alpine

# Generate a self-signed certificate for localhost at build time.
# This keeps the private key out of git and gives every machine that builds
# the image an HTTPS-capable nginx with zero extra setup steps.
RUN apk add --no-cache openssl \
 && mkdir -p /etc/nginx/certs \
 && openssl req -x509 -nodes -newkey rsa:2048 -days 3650 \
      -keyout /etc/nginx/certs/localhost.key \
      -out  /etc/nginx/certs/localhost.crt \
      -subj "/CN=localhost" \
      -addext "subjectAltName=DNS:localhost,IP:127.0.0.1"

# nginx loads every *.conf in conf.d, so HTTP (default.conf) and HTTPS (ssl.conf)
# server blocks are served simultaneously.
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docker/nginx/ssl.conf     /etc/nginx/conf.d/ssl.conf
