$paths = @('.env','borg_fish.sql','database/exports/borgfish-infinityfree-20260409-110415.sql','database/exports/borgfish-infinityfree-fk-safe-20260409-110448.sql','public/build','vendor','node_modules','storage/logs','.phpunit.result.cache','backup-before-reset.bundle')
foreach ($p in $paths) {
  Write-Output "=== $p ==="
  if (Test-Path -LiteralPath $p) { Write-Output "Exists: True" } else { Write-Output "Exists: False" }
  try {
    $ig = git check-ignore -v -- $p 2>$null
    if ($ig) { Write-Output "Ignored: $ig" } else { Write-Output "Ignored: NO" }
  } catch {
    Write-Output "Ignored: NO"
  }
}
