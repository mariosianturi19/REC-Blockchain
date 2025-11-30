#!/bin/bash

# Auto-Sync Script untuk Laravel Scheduler
# Script ini akan menjalankan Laravel scheduler yang akan mengeksekusi:
# - Auto-sync blockchain status setiap 3 menit
# - Auto-complete Step 5 setiap 2 menit
# - Auto-fix incomplete workflows setiap 10 menit

echo "🚀 Starting REC Blockchain Auto-Sync Scheduler..."
echo "📅 Started at: $(date)"

# Masuk ke direktori Laravel
cd /home/najla/Downloads/REC-Blockchain/Capstone_Renewa

# Buat direktori logs jika belum ada
mkdir -p storage/logs

# Jalankan scheduler dalam loop
echo "⏰ Laravel Scheduler is now running..."
echo "🔄 Auto-sync every 3 minutes"
echo "🚀 Auto-complete every 2 minutes"
echo "🔧 Auto-fix every 10 minutes"
echo ""
echo "Press Ctrl+C to stop"
echo "=========================="

while true; do
    # Jalankan Laravel scheduler
    php artisan schedule:run >> storage/logs/scheduler.log 2>&1
    
    # Tampilkan timestamp
    echo "$(date '+%Y-%m-%d %H:%M:%S') - Scheduler tick"
    
    # Tunggu 1 menit sebelum check lagi
    sleep 60
done