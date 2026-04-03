@echo off
git config user.email "vicbrak2@users.noreply.github.com"
git config user.name "vicbrak2"
git add .
git commit -m "chore: initial commit with README for QS Manager"
git branch -M main
git remote add origin https://github.com/vicbrak2/qs-manager.git 2>nul
git push -u origin main
