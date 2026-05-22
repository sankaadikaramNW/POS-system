# Google Cloud Platform (GCP) Deployment & CI/CD Setup Guide

This guide provides step-by-step instructions for establishing your GCP resources and linking them to your GitHub repository to enable the automatic Dockerized CI/CD pipeline.

---

## 📋 Architecture Overview

The pipeline executes the following workflow:
1. **Push to `main`/`master`** branch triggers the GitHub Actions workflow.
2. **GitHub Runner** builds the Docker container from the `Dockerfile`.
3. **Container Image** is securely pushed to the **Google Artifact Registry (GAR)**.
4. **Cloud Run Service** pulls the new container image and deploys it serverlessly.
5. **Database Traffic** routes securely to a managed **Google Cloud SQL** (MySQL) instance using a Unix socket.

---

## 🛠️ Step 1: Initialize Your Google Cloud Project

You can run these steps directly in the [Google Cloud Console](https://console.cloud.google.com) or using the **Google Cloud SDK (`gcloud` CLI)**.

### 1. Enable Required Google APIs
Enable the services required for storage, serverless running, database clients, and CI/CD:
```bash
gcloud services enable \
    artifactregistry.googleapis.com \
    run.googleapis.com \
    sqladmin.googleapis.com \
    sql-component.googleapis.com
```

---

## 📦 Step 2: Set Up Google Artifact Registry

Artifact Registry is Google Cloud's modern Docker registry.

1. Create a private Docker repository in your preferred region (e.g., `us-central1`):
   ```bash
   gcloud artifacts repositories create pos-system-repo \
       --repository-format=docker \
       --location=us-central1 \
       --description="Docker Repository for Fashion POS System"
   ```

---

## 🗄️ Step 3: Set Up Managed Google Cloud SQL (MySQL)

Cloud SQL is a fully managed MySQL service that integrates natively with Cloud Run.

### 1. Create a MySQL Instance
Create a lightweight MySQL 8.0 instance (using the cost-efficient `db-f1-micro` tier for testing/small-scale usage):
```bash
gcloud sql instances create fashion-pos-db \
    --database-version=MYSQL_8_0 \
    --tier=db-f1-micro \
    --region=us-central1
```

### 2. Create the Database
Create the database inside the Cloud SQL instance:
```bash
gcloud sql databases create fashion_pos --instance=fashion-pos-db
```

### 3. Create the Database User
Create a secure database user (replace `YOUR_SECURE_PASSWORD` with a strong password):
```bash
gcloud sql users create pos_admin \
    --instance=fashion-pos-db \
    --password="YOUR_SECURE_PASSWORD"
```

### 4. Seed the Database
Import your `sql/fashion_pos.sql` file into the Cloud SQL instance.
- **Option A (Via Cloud Storage):**
  1. Upload `sql/fashion_pos.sql` to a Google Cloud Storage bucket.
  2. Go to GCP Console -> SQL -> **fashion-pos-db** -> click **Import**.
  3. Select your SQL file and database (`fashion_pos`), then click Import.
- **Option B (Via local command line using Cloud SQL Auth Proxy):**
  1. Download the [Cloud SQL Auth Proxy](https://cloud.google.com/sql/docs/mysql/sql-proxy).
  2. Start the proxy:
     ```bash
     ./cloud-sql-proxy fashion-pos-db --port 3306
     ```
  3. In a separate terminal, import the SQL dump:
     ```bash
     mysql -h 127.0.0.1 -u pos_admin -p fashion_pos < sql/fashion_pos.sql
     ```

### 5. Get the Connection Name
Run this command to get the instance connection name:
```bash
gcloud sql instances describe fashion-pos-db --format="value(connectionName)"
```
*It will be in the format: `PROJECT_ID:REGION:INSTANCE_NAME` (e.g. `my-gcp-project:us-central1:fashion-pos-db`). Write this down!*

---

## 🔑 Step 4: Create a Service Account for GitHub Actions

To allow GitHub Actions to build and deploy to Google Cloud, you must provide it with a secure Service Account.

### 1. Create the Service Account
```bash
gcloud iam service-accounts create github-deployer \
    --description="Service Account for GitHub Actions CI/CD" \
    --display-name="GitHub Deployer"
```

### 2. Assign IAM Permissions
Assign the necessary roles to this service account:

- **Artifact Registry Writer:** To push Docker images.
  ```bash
  gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
      --member="serviceAccount:github-deployer@YOUR_PROJECT_ID.iam.gserviceaccount.com" \
      --role="roles/artifactregistry.writer"
  ```

- **Cloud Run Developer:** To deploy new service versions.
  ```bash
  gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
      --member="serviceAccount:github-deployer@YOUR_PROJECT_ID.iam.gserviceaccount.com" \
      --role="roles/run.developer"
  ```

- **Service Account User:** To run the container under the default compute service account.
  ```bash
  gcloud iam service-accounts add-iam-policy-binding \
      $(gcloud projects describe YOUR_PROJECT_ID --format="value(projectNumber)")-compute@developer.gserviceaccount.com \
      --member="serviceAccount:github-deployer@YOUR_PROJECT_ID.iam.gserviceaccount.com" \
      --role="roles/iam.serviceAccountUser"
  ```

- **Cloud SQL Client:** Required for Cloud Run to connect to the database.
  ```bash
  gcloud projects add-iam-policy-binding YOUR_PROJECT_ID \
      --member="serviceAccount:github-deployer@YOUR_PROJECT_ID.iam.gserviceaccount.com" \
      --role="roles/cloudsql.client"
  ```

### 3. Generate the JSON Credentials Key
Generate and download the authentication key:
```bash
gcloud iam service-accounts keys create gcp-key.json \
    --iam-account="github-deployer@YOUR_PROJECT_ID.iam.gserviceaccount.com"
```
*Copy the complete text contents of `gcp-key.json` and delete the file from your local machine to prevent credential leaks.*

---

## 🔒 Step 5: Configure GitHub Secrets

Go to your GitHub repository -> **Settings** -> **Secrets and variables** -> **Actions** -> click **New repository secret** and add the following:

| Secret Name | Example Value | Description |
| :--- | :--- | :--- |
| `GCP_PROJECT_ID` | `fashion-pos-34289` | Your Google Cloud project ID. |
| `GCP_REGION` | `us-central1` | GCP region for Artifact Registry and Cloud Run. |
| `GCP_ARTIFACT_REPOSITORY` | `pos-system-repo` | Name of the GAR repository you created in Step 2. |
| `GCP_SA_KEY` | `{ "type": "service_account", ... }` | Paste the **entire** content of the `gcp-key.json` file. |
| `GCP_CLOUD_SQL_CONNECTION_NAME` | `fashion-pos-34289:us-central1:fashion-pos-db` | The instance connection name from Step 3.5. |
| `DB_NAME` | `fashion_pos` | The name of the MySQL database. |
| `DB_USER` | `pos_admin` | The MySQL user name. |
| `DB_PASS` | `pos_secure_password` | The MySQL user password. |

---

## 🚀 Step 6: Trigger the CI/CD Pipeline

Once the secrets are set up:
1. Commit the newly added files to your repository:
   ```bash
   git add .
   git commit -m "feat: Add Docker and Google Cloud CI/CD configuration"
   git push origin main
   ```
2. In GitHub, go to the **Actions** tab to see your pipeline trigger, build, and deploy.
3. Once completed successfully, the service URL will be printed at the bottom of the log, letting you access the serverless live app!

---

## 🐳 Quick Start: Run Locally via Docker Compose

Want to run the exact same setup locally on your computer with a single command?

1. Launch both containers:
   ```bash
   docker-compose up --build -d
   ```
2. Your database will be automatically created and populated.
3. Access the application in your browser at:
   `http://localhost:8080`
4. Stop the containers while maintaining database state:
   ```bash
   docker-compose down
   ```
