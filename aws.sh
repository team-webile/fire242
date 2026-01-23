#!/bin/bash

# AWS credentials
export AWS_ACCESS_KEY_ID="AKIAXYKJV4ZBQQ6ZJIXN"
export AWS_SECRET_ACCESS_KEY="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"
export AWS_DEFAULT_REGION="us-east-2"

# Source & S3 destination
SOURCE_DIR="/var/www/html/storage/app/public/voter_cards_images"
S3_DEST="s3://fire242/voter_cards_images"

echo "Uploading all images from $SOURCE_DIR to $S3_DEST ..."
aws s3 cp "$SOURCE_DIR" "$S3_DEST" --recursive

if [ $? -eq 0 ]; then
    echo "✅ Upload complete!"
else
    echo "❌ Upload failed!"
fi
