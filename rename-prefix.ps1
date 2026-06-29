$replacements = @(
    @{ From = 'pod_connector_'; To = 'sac_' },
    @{ From = 'POD_CONNECTOR_'; To = 'SAC_' },
    @{ From = 'pod-connector/v1'; To = 'sac/v1' },
    @{ From = "Text Domain: pod-ai-connector"; To = "Text Domain: store-ai-connector" }
)

Get-ChildItem -Recurse -Filter "*.php" | ForEach-Object {
    $content = Get-Content $_.FullName -Raw -Encoding UTF8
    $changed = $false
    foreach ($r in $replacements) {
        if ($content -match [regex]::Escape($r.From)) {
            $content = $content -replace [regex]::Escape($r.From), $r.To
            $changed = $true
        }
    }
    if ($changed) {
        Set-Content $_.FullName $content -Encoding UTF8 -NoNewline
        Write-Host "Updated: $($_.Name)"
    }
}
Write-Host "Done. Verifying..."

# Verify không còn prefix cũ
$found = Get-ChildItem -Recurse -Filter "*.php" | Select-String "pod_connector_"
if ($found) { Write-Host "WARNING: Still found old prefix!" ; $found } 
else { Write-Host "CLEAN: No old prefix found" }