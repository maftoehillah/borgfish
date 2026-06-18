# Scan unignored files for likely secrets and print first 500 matches
$files = git ls-files --others --exclude-standard | Where-Object { $_ -ne '' }
if (-not $files) {
  Write-Output 'No unignored files to scan.'
  exit 0
}
$patterns = @(
  'TRIPAY',
  'TRIPAY_PRIVATE',
  'TRIPAY_API',
  'TRIPAY_MERCHANT_CODE',
  'GOOGLE_CLIENT_SECRET',
  'GOOGLE_CLIENT_ID',
  'GOOGLE_REDIRECT_URI',
  'AWS_ACCESS_KEY_ID',
  'AWS_SECRET_ACCESS_KEY',
  '-----BEGIN .*PRIVATE KEY-----',
  'BEGIN RSA PRIVATE KEY',
  'PRIVATE KEY',
  'client_secret',
  'client_id',
  '\$2[ayb]\$',
  'password',
  'passwd',
  'Authorization: Bearer',
  'Bearer [A-Za-z0-9\._\-]{20,}',
  'TRIPAY_PRIVATE_KEY'
)

Write-Output "Scanning $(($files | Measure-Object).Count) files for probable secrets..."
$matches = Select-String -Path $files -Pattern $patterns -AllMatches -CaseSensitive:$false -ErrorAction SilentlyContinue
if (-not $matches) {
  Write-Output 'No probable secrets found in unignored files.'
  exit 0
}

$matches | Select-Object Path,LineNumber,Line | Select-Object -First 500 | Format-Table -AutoSize
$matches.Count | ForEach-Object { Write-Output "Total matches: $_" }
