#!/bin/bash
# Build release zip: thư mục gốc "store-ai-connector-main", loại file dev
set -e
VERSION=$(grep -oP "Version:\s+\K[\d.]+" store-ai-connector.php | head -1)
OUT="/tmp/store-ai-connector-${VERSION}.zip"
TMPDIR=$(mktemp -d)
mkdir -p "$TMPDIR/store-ai-connector-main"

# Copy files, loại trừ dev files
rsync -a --exclude='.git' \
  --exclude='build-zip.ps1' \
  --exclude='rename-prefix.ps1' \
  --exclude='test-*.js' \
  --exclude='test_*.php' \
  --exclude='*.mjs' \
  --exclude='package.json' \
  --exclude='clean-invalid-gallery.php' \
  --exclude='build-release-zip.sh' \
  . "$TMPDIR/store-ai-connector-main/"

cd "$TMPDIR"
zip -qr "$OUT" store-ai-connector-main
rm -rf "$TMPDIR"
echo "Built: $OUT"
ls -lh "$OUT"
