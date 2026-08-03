# MicroPowerManager Development Instructions

========================================
BACKEND
========================================

Step 1 - Clone & Setup

~ cd /var/www
~ rm -rf micropowermanager
~ git clone https://github.com/rohitwebstep/micropowermanager.git

~ cd micropowermanager
~ cd backend

~ sudo cp .env.example .env

~ composer install --ignore-platform-reqs

~ php artisan config:clear
~ php artisan route:clear
~ php artisan view:clear
~ php artisan cache:clear
~ php artisan clear-compiled


Step 2 - Refresh Database (Optional)

# Run only if you want to refresh all migrations.
~ php artisan migrate:refresh


Step 3 - Start Backend Service

~ sudo systemctl daemon-reload
~ sudo systemctl enable micropowermanager.service
~ sudo systemctl start micropowermanager.service


========================================
FRONTEND
========================================

Step 1 - Environment Setup

~ cd ../frontend
~ sudo cp .env.example .env


Step 2 - Install Dependencies

~ npm install


Step 3 - Start Frontend

~ pm2 stop micropower-frontend
~ pm2 delete micropower-frontend
~ pm2 start "npm run serve -- --port 8001" --name micropower-frontend

# View frontend logs
~ pm2 logs micropower-frontend


========================================
FINAL STEP (Required Permissions)
========================================

~ cd /var/www/micropowermanager/backend

~ sudo mkdir -p storage/logs

~ sudo chown -R www-data:www-data storage
~ sudo chown -R www-data:www-data bootstrap/cache

~ sudo chmod -R 775 storage
~ sudo chmod -R 775 bootstrap/cache


========================================
DEBUGGING
========================================

# View backend logs in real time
~ sudo journalctl -u micropowermanager.service -f