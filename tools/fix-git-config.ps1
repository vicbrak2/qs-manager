# Script para corregir manualmente la configuración de git que rompe Antigravity
# Uso: .\tools\fix-git-config.ps1

Write-Host "Revisando configuración de Git..." -ForegroundColor Cyan

$config = git config --list --local
if ($config -like "*extensions.worktreeConfig*") {
    Write-Host "Detectada configuración incompatible: extensions.worktreeConfig=true" -ForegroundColor Yellow
    git config --unset extensions.worktreeConfig
    Write-Host "¡Corregido! Ahora puedes reiniciar la sesión de Antigravity si era necesario." -ForegroundColor Green
} else {
    Write-Host "No se detectó la configuración incompatible. Todo parece estar en orden." -ForegroundColor Gray
}
