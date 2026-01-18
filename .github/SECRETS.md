# GitHub Secrets Setup Guide

This document explains how to configure GitHub secrets for the CI/CD pipeline.

## Required Repository Secrets

Go to your GitHub repository → **Settings** → **Secrets and variables** → **Actions** → **New repository secret**

### 1. GCP_PROJECT_ID
- **Name**: `GCP_PROJECT_ID`
- **Value**: Your Google Cloud Project ID (e.g., `my-project-12345`)
- **How to find**: Run `gcloud config get-value project` or check in GCP Console

### 2. GCP_SA_KEY
- **Name**: `GCP_SA_KEY`
- **Value**: The entire JSON content of your service account key
- **How to create**:
  ```bash
  # Create service account
  gcloud iam service-accounts create github-actions \
    --display-name="GitHub Actions"
  
  # Grant necessary permissions
  gcloud projects add-iam-policy-binding PROJECT_ID \
    --member="serviceAccount:github-actions@PROJECT_ID.iam.gserviceaccount.com" \
    --role="roles/run.admin"
  
  gcloud projects add-iam-policy-binding PROJECT_ID \
    --member="serviceAccount:github-actions@PROJECT_ID.iam.gserviceaccount.com" \
    --role="roles/artifactregistry.writer"
  
  gcloud projects add-iam-policy-binding PROJECT_ID \
    --member="serviceAccount:github-actions@PROJECT_ID.iam.gserviceaccount.com" \
    --role="roles/iam.serviceAccountUser"
  
  # Create and download key
  gcloud iam service-accounts keys create key.json \
    --iam-account=github-actions@PROJECT_ID.iam.gserviceaccount.com
  
  # Copy the entire content of key.json and paste as secret value
  cat key.json
  ```

## Required Environment Secrets

Create two environments in GitHub: **staging** and **production**

Go to **Settings** → **Environments** → **New environment**

### For Each Environment (staging and production):

#### APP_KEY
- **Value**: Laravel application key
- **Generate**: `php artisan key:generate --show`

#### DB_HOST
- **staging**: `/cloudsql/PROJECT_ID:REGION:book-review-staging`
- **production**: `/cloudsql/PROJECT_ID:REGION:book-review-prod`

#### DB_PORT
- **Value**: `3306`

#### DB_DATABASE
- **Value**: `book_store`

#### DB_USERNAME
- **Value**: Your database username (e.g., `bookuser`)

#### DB_PASSWORD
- **Value**: Your secure database password

#### REDIS_HOST
- **Value**: IP address of your Redis instance
- **How to find**: 
  ```bash
  gcloud redis instances describe book-review-staging \
    --region=us-central1 \
    --format="get(host)"
  ```

## Environment Variables

For each environment, also set these **Variables** (not secrets):

### Staging:
- `APP_ENV` = `staging`
- `APP_DEBUG` = `true`

### Production:
- `APP_ENV` = `production`
- `APP_DEBUG` = `false`

## Verification Checklist

Before pushing to trigger deployment:

- [ ] Repository has `GCP_PROJECT_ID` secret
- [ ] Repository has `GCP_SA_KEY` secret
- [ ] `staging` environment is created with all secrets
- [ ] `production` environment is created with all secrets
- [ ] Service account has necessary IAM roles
- [ ] Artifact Registry repository exists
- [ ] Cloud SQL instances are created
- [ ] Redis instances are created

## Testing Secrets

You can test if secrets are properly configured by pushing to the dev branch. The workflow will fail early if secrets are missing.

## Security Best Practices

1. Never commit service account keys to the repository
2. Rotate service account keys regularly
3. Use separate service accounts for preprod and production (recommended)
4. Enable environment protection rules for production
5. Require approval for production deployments
6. Delete downloaded key.json after adding to GitHub secrets

## Troubleshooting

### Error: "credentials_json secret is not set"
- Make sure you've added `GCP_SA_KEY` as a repository secret
- Check that the secret name is exactly `GCP_SA_KEY` (case-sensitive)
- Verify the secret contains valid JSON

### Error: "Permission denied"
- Ensure service account has all required IAM roles
- Check that service account email is correct in the binding commands

### Error: "Project not found"
- Verify `GCP_PROJECT_ID` matches your actual project ID
- Ensure billing is enabled on the project
