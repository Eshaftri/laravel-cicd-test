# Google Cloud Run Deployment Guide

This guide will help you set up CI/CD deployment to Google Cloud Run with two environments: Staging and Production.

## Prerequisites

1. Google Cloud Project with billing enabled
2. GitHub repository for your code
3. Google Cloud SQL (MySQL) instances for each environment
4. Google Cloud Memorystore (Redis) instances for caching

## Google Cloud Setup

### 1. Enable Required APIs

```bash
gcloud services enable \
  run.googleapis.com \
  cloudbuild.googleapis.com \
  artifactregistry.googleapis.com \
  sqladmin.googleapis.com \
  redis.googleapis.com
```

### 2. Create Artifact Registry Repository

```bash
gcloud artifacts repositories create book-review \
  --repository-format=docker \
  --location=us-central1 \
  --description="Book Review Application"
```

### 3. Create Cloud SQL Instances

```bash
# Staging
gcloud sql instances create book-review-staging \
  --database-version=MYSQL_8_0 \
  --tier=db-g1-small \
  --region=us-central1

# Production
gcloud sql instances create book-review-prod \
  --database-version=MYSQL_8_0 \
  --tier=db-n1-standard-2 \
  --region=us-central1 \
  --availability-type=REGIONAL
```

### 4. Create Databases

```bash
# For each environment
gcloud sql databases create book_store --instance=book-review-staging
gcloud sql databases create book_store --instance=book-review-prod
```

### 5. Create Database Users

```bash
# Staging
gcloud sql users create bookuser \
  --instance=book-review-staging \
  --password=SECURE_PASSWORD_HERE

# Production
gcloud sql users create bookuser \
  --instance=book-review-prod \
  --password=SECURE_PASSWORD_HERE
```

### 6. Create Redis (Memorystore) Instances

```bash
# Staging
gcloud redis instances create book-review-staging \
  --size=2 \
  --region=us-central1 \
  --tier=standard

# Production
gcloud redis instances create book-review-prod \
  --size=5 \
  --region=us-central1 \
  --tier=standard \
  --replica-count=1
```

### 7. Create Service Account for GitHub Actions

```bash
# Create service account
gcloud iam service-accounts create github-actions \
  --description="Service account for GitHub Actions" \
  --display-name="GitHub Actions"

# Grant necessary roles
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
gcloud iam service-accounts keys create github-actions-key.json \
  --iam-account=github-actions@PROJECT_ID.iam.gserviceaccount.com
```

## GitHub Repository Setup

### 1. Required Secrets (Repository → Settings → Secrets and variables → Actions)

Create these **Secrets**:

```
GCP_PROJECT_ID=your-project-id
GCP_SA_KEY=<contents of github-actions-key.json>
```

### 2. Environment-Specific Secrets

For each environment (staging, production), create these secrets:

**Staging Environment:**
```
APP_KEY=base64:GENERATED_KEY_HERE
DB_HOST=/cloudsql/PROJECT_ID:us-central1:book-review-staging
DB_PORT=3306
DB_DATABASE=book_store
DB_USERNAME=bookuser
DB_PASSWORD=YOUR_PASSWORD
REDIS_HOST=REDIS_IP_ADDRESS
```

**Production Environment:**
```
APP_KEY=base64:GENERATED_KEY_HERE
DB_HOST=/cloudsql/PROJECT_ID:us-central1:book-review-prod
DB_PORT=3306
DB_DATABASE=book_store
DB_USERNAME=bookuser
DB_PASSWORD=YOUR_PASSWORD
REDIS_HOST=REDIS_IP_ADDRESS
```

### 3. Environment Variables

For each environment, create these **Variables**:

**Staging:**
```
APP_ENV=staging
APP_DEBUG=true
```

**Production:**
```
APP_ENV=production
APP_DEBUG=false
```

## Generate Application Keys

```bash
# Generate a key locally
php artisan key:generate --show
```

Use the output as the `APP_KEY` secret for each environment.

## Branch Strategy

- `develop` → Deploys to Development environment
- `staging` → Deploys to Staging environment
- `main` → Deploys to Production environment

## Deployment Workflow

1. **Development**: Push to `develop` branch
   ```bash
   git checkout develop
   git add .
   git commit -m "Your changes"
   git push origin develop
   ```

2. **Staging**: Merge develop to staging
   ```bash
   git checkout staging
   preprod` → Deploys to Pre-Production environment
- `main` → Deploys to Production environment

## Deployment Workflow

1. **Pre-Production**: Push to `preprod` branch
   ```bash
   git checkout preprod
   git add .
   git commit -m "Your changes"
   git push origin preprod
   ```

2. **Production**: Merge preprod to main
   ```bash
   git checkout main
   git merge preprod
   git push origin main
   ```

## First Time Deployment

After setting up everything:

1. Push to dev branch
2. GitHub Actions will automatically:
   - Run tests
   - Build Docker image
   - Push to Artifact Registry
   - Deploy to Cloud Run
   - Run database migrations
3. Once validated in preprod, merge to main for production deployment
```bash
# View logs
gcloud run services logs read book-review-prod --limit=50

# Describe service
gcloud run services describe book-review-prod --region=us-central1
```

## Rollback

```bash
# List revisions
gcloud run revisions list --service=book-review-prod

# Rollback to previous revision
gcloud run services update-traffic book-review-prod \
  --to-revisions=REVISION_NAME=100
```

## Cost Optimization

- Development: Minimum instances = 0 (scales to zero)
- Staging: Minimum instances = 0
- Production: Minimum instances = 1 (for better availability)

## Security Checklist

- [ ] All secrets stored in GitHub Secrets
- Pre-Production: Minimum instances = 0 (scales to zero)rds
- [ ] Cloud SQL uses private IP when possible
- [ ] Cloud Run service uses least privilege service account
- [ ] Enable Cloud Armor for DDoS protection
- [ ] Set up Cloud Monitoring alerts

## Troubleshooting

### Build Fails
- Check GitHub Actions logs
- Verify all secrets are set correctly
- Ensure GCP service account has proper permissions

### Database Connection Issues
- Verify Cloud SQL instance is running
- Check database credentials
- Ensure Cloud Run has Cloud SQL Admin API enabled

### Application Errors
- Check Cloud Run logs: `gcloud run services logs read SERVICE_NAME`
- Verify environment variables are set correctly
- Check Laravel logs in Cloud Logging

## Additional Resources

- [Google Cloud Run Documentation](https://cloud.google.com/run/docs)
- [Laravel Deployment Documentation](https://laravel.com/docs/deployment)
- [GitHub Actions Documentation](https://docs.github.com/en/actions)
