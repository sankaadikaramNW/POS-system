$content = Get-Content 'c:\xampp\htdocs\POS\sales.php' -Raw
$old = 'onclick="clearCart()" style="font-size:0.8rem; width: 25%;">'
$new = 'id="btnCancelTransaction" onclick="cancelPOSTransaction()" style="font-size:0.8rem; width: 25%;">'
$content = $content.Replace($old, $new)
Set-Content 'c:\xampp\htdocs\POS\sales.php' -Value $content -Encoding UTF8
Write-Host "Done. Replacements made."
