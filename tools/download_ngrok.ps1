if (-not (Test-Path 'tools')) { New-Item -ItemType Directory -Path 'tools' | Out-Null }
$url = 'https://bin.equinox.io/c/4VmDzA7iaHb/ngrok-stable-windows-amd64.zip'
$out = Join-Path 'tools' 'ngrok.zip'
Write-Output "Downloading ngrok from $url to $out"
try {
    Invoke-WebRequest -Uri $url -OutFile $out -UseBasicParsing -ErrorAction Stop
} catch {
    Write-Error "Failed to download ngrok: $_"
    exit 1
}
Write-Output "Extracting..."
try {
    Expand-Archive -Path $out -DestinationPath 'tools' -Force
    Remove-Item $out -Force
} catch {
    Write-Error "Failed to extract ngrok: $_"
    exit 1
}
Write-Output "Done. Files:"
Get-ChildItem 'tools' | Select-Object Name, Length
