# Build WordPress plugin zip with correct folder structure.
# Output: ../store-ai-connector.zip
# Correct: store-ai-connector/store-ai-connector.php
# Wrong:   store-ai-connector/store-ai-connector/store-ai-connector.php

$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$zip  = Join-Path $root 'store-ai-connector.zip'

if (Test-Path $zip) { Remove-Item $zip -Force }

tar -a -c -f $zip `
    --exclude=.git `
    --exclude=node_modules `
    --exclude=.cursor `
    --exclude=*.zip `
    -C $root `
    store-ai-connector

$main = tar -tf $zip | Select-String '^store-ai-connector/store-ai-connector\.php$'
$bad  = tar -tf $zip | Select-String 'store-ai-connector/store-ai-connector/'

if (-not $main) { throw 'ZIP invalid: missing store-ai-connector/store-ai-connector.php' }
if ($bad)       { throw 'ZIP invalid: nested store-ai-connector/store-ai-connector/ folder detected' }

Write-Host "OK: $zip"
Write-Host "Main file: $($main.Line)"
