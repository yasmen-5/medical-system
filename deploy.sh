#!/bin/bash

# Medical System Deployment Script for Render.com

echo "🚀 Starting deployment process..."

# Install dependencies
echo "📦 Installing PHP dependencies..."
composer install --no-dev --optimize-autoloader

# Generate application key
echo "🔑 Generating application key..."
php artisan key:generate

# Run migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Create storage link
echo "🔗 Creating storage link..."
php artisan storage:link

# Install and build frontend assets
echo "🎨 Installing and building frontend assets..."
npm install
npm run build

# Clear and cache configurations
echo "⚙️ Optimizing application..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "✅ Deployment complete!"