# Swag Inventory Management System

A modern, responsive inventory tracking application built with **Vue.js**, **PHP**, and **Tailwind CSS**, powered by **Airtable**.

## 🚀 Features

- **Real-time Dashboard**: Monitor total items, low stock alerts, and transaction trends at a glance.
- **Unified Transaction System**: Single, intuitive interface for Check-ins, Check-outs, and Restocking.
- **Smart Inventory Logic**: Automatically prevents checking out more items than available and updates stock levels instantly.
- **Comprehensive Reporting**: 
  - **Visual Analytics**: Interactive charts showing stock distribution and transaction activity.
  - **Transaction History**: Filterable log with type filtering.
  - **"Most Popular Item"** analytics.
  - **Dedicated "Low Stock"** view for quick restocking.
- **Modern UI/UX**:
  - Clean, distraction-free design.
  - Instant feedback with toast notifications.
  - Smooth animations and transitions.

## 🛠 Tech Stack

- **Frontend**: Vue.js 3 (Composition/Options API), Tailwind CSS
- **Backend**: PHP 8+ (Custom Router & API Layer)
- **Database**: Airtable API

## 📦 Installation & Setup

1. **Navigate to the project directory**:
   ```bash
   cd inventory
   ```

2. **Configuration**:
   - The application is pre-configured with the Airtable API key and Base ID in `config.php`.
   - Ensure you have PHP installed on your machine.

3. **Start the Development Server**:
   ```bash
   php -S localhost:8001 -t . router.php
   ```

4. **Access the App**:
   - Open your browser and visit: `http://localhost:8001`

## 📂 Project Structure

```
inventory/
├── api/
│   ├── index.php           # Main API Controller
│   └── services/
│       └── AirtableService.php # Airtable API Wrapper
├── assets/
│   └── js/
│       └── app.js          # Vue.js Application Logic
├── index.html              # Main Single Page Application (SPA)
├── router.php              # PHP Router for Local Development
└── config.php              # App Configuration
```

## 💡 Usage Guide

- **Dashboard**: View high-level stats. Items with low stock (below threshold) appear in red.
- **Inventory**: Browse all items. Use the search bar to find specific swag.
- **New Transaction**: Click the top-right button to Log a Check In, Check Out, or Restock.
- **History**: View past transactions. Use the tabs to filter by transaction type.
