@echo off
powershell -Command "Invoke-WebRequest -Uri http://localhost/UniKL%%20ACE/technician/cron_reminder.php" > NUL 2>&1