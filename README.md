# SaaS in Business Management System

An AI-powered WordPress plugin for managing inventory, sales, expenses and business performance with intelligent business insights and recommendations.

---

## Team Information

### Team Name
**CSE4104-7B-T03 — Stack Builders**

### Section
**7B**

### Team Members

| Name | ID | Role |
|------|------|------|
| Imran Kabir | 11230121102 | Group Leader |
| Sajal Biswas | 11230121114 | Team Member |
| Borhan Kabir | 11230121126 | Team Member |
| Neeaj Morshed | 11230121117 | Team Member |

---

# Project Description

The **SaaS in Business Management System** is a WordPress-based business management plugin developed as a semester project for the **CSE4104** course.

The system is designed to help small and medium-sized businesses manage important day-to-day operations from a centralised dashboard.

The plugin combines inventory management, sales tracking, expense management, business performance monitoring and AI-assisted business analysis.

The objective is to reduce manual work, improve data organisation and provide business owners with useful information for better decision-making.

---

# Core Features

The system includes the following major features:

- User authentication and profile management
- Business management dashboard
- Inventory and stock management
- Product management
- Low-stock detection
- Sales management
- Automatic stock adjustment after sales
- Expense management
- Business performance monitoring
- REST API integration
- AI business analysis
- AI-generated recommendations
- AI report management
- Secure AI configuration
- Responsive WordPress admin interface
- Input validation and error handling

---

# Technology Stack

## Platform
- WordPress

## Backend
- PHP
- WordPress Plugin API
- WordPress REST API

## Frontend
- HTML5
- CSS3
- JavaScript

## Database
- MySQL
- WordPress `$wpdb`

## AI Integration
- Google Gemini API

## Development and Testing
- Visual Studio Code
- Postman
- Git
- GitHub

---

# System Architecture

The application follows a modular architecture:

```text
User
  ↓
WordPress Admin Interface
  ↓
Frontend JavaScript
  ↓
WordPress REST API
  ↓
Service / Business Logic Layer
  ↓
Model / Database Layer
  ↓
MySQL Database
```

For AI functionality:

```text
Business Data
     ↓
WordPress Backend
     ↓
AI Service
     ↓
Google Gemini API
     ↓
AI Response
     ↓
Response Validation
     ↓
Business Insights / Recommendations
     ↓
Dashboard
```

---

# Repository Structure

The current project is organised into the following major components:

```text
saas-in-business-management-system/
│
├── admin/
├── api/
├── assets/
├── database/
├── design/
├── diagrams/
├── documentation/
├── includes/
├── middleware/
├── models/
├── services/
│
├── index.php
├── saas-business-management.php
├── uninstall.php
└── README.md
```

## Folder Description

### `admin/`

Contains the WordPress administration interface and functionality for the business management dashboard.

The admin area handles interfaces for features such as:

- Dashboard
- Inventory
- Sales
- Expenses
- AI insights
- Profile
- Settings

### `api/`

Contains the WordPress REST API implementation used to connect application functionality with the backend.

The APIs support major modules such as:

- Authentication
- User/profile
- Inventory
- Sales
- Expenses
- AI functionality

### `assets/`

Contains frontend resources used by the plugin, including:

- CSS
- JavaScript
- Images and other interface assets

### `database/`

Contains database-related resources and database structure information.

The system stores information related to:

- Business/user profiles
- Products
- Inventory
- Sales
- Sale items
- Expenses
- AI reports

### `includes/`

Contains the main plugin classes and supporting functionality required to initialise and operate the plugin.

### `middleware/`

Contains authentication, permission and security-related middleware.

### `models/`

Contains the application's data models and database interaction logic.

### `services/`

Contains business logic for the main application modules.

### `design/`

Contains project design resources.

### `diagrams/`

Contains system diagrams such as:

- System architecture
- Activity diagram
- User flow
- AI workflow
- Database/ER diagrams

### `documentation/`

Contains academic and technical project documentation.

---

# Database Design

The plugin uses MySQL through the WordPress database layer.

The database supports the following major entities:

1. Business/User Profile
2. Products/Inventory
3. Sales
4. Sale Items
5. Expenses
6. AI Reports

The implementation uses the WordPress database prefix dynamically instead of assuming the prefix is always `wp_`.

The database design includes:

- Primary keys
- Appropriate indexes
- Data relationships
- Appropriate data types
- Created/updated timestamps
- Business/user ownership

The plugin is designed to initialise the required database structure during plugin activation.

---

# Authentication and Security

The system uses WordPress authentication mechanisms rather than maintaining a separate plain-text password system.

Security measures include:

- Secure password handling
- Authentication checks
- Permission and capability checks
- REST API permission callbacks
- Input sanitisation
- Input validation
- Output escaping
- Prepared SQL queries
- Secure error handling
- Protected API endpoints

Sensitive credentials such as AI API keys should not be exposed through frontend JavaScript.

---

# Inventory Management

The Inventory module provides functionality for:

- Adding products
- Viewing products
- Updating products
- Deleting products
- Searching products
- Product categories
- SKU management
- Selling price
- Cost price
- Stock quantity
- Low-stock threshold
- Low-stock detection

Business/user ownership is used to prevent users from accessing another user's business information.

---

# Sales Management

The Sales module provides:

- Create sale
- View sales
- View individual sale
- Product selection
- Sale items
- Quantity management
- Unit price
- Subtotal calculation
- Total calculation
- Sale date

When a valid sale is recorded, the system can update the corresponding inventory quantity.

Important financial calculations are performed on the backend rather than relying only on frontend values.

---

# Expense Management

The Expense module provides:

- Add expense
- View expenses
- Update expense
- Delete expense
- Expense categories
- Expense amount
- Description
- Expense date
- Business/user ownership

Expense information is also used when calculating business performance and preparing information for AI analysis.

---

# Business Dashboard

The dashboard provides a central view of important business information.

Dashboard information includes:

- Total sales
- Total expenses
- Total products
- Low-stock products
- Recent sales
- Recent expenses
- Sales summary
- Expense summary
- Inventory summary

Dashboard information is intended to be generated from actual application/database data rather than hardcoded business statistics.

---

# REST API

The plugin uses the WordPress REST API.

## Base Namespace

```text
/wp-json/saas-bms/v1/
```

## Authentication

```text
POST /register
POST /login
POST /logout
GET  /profile
PUT  /profile
```

## Inventory

```text
GET    /inventory
GET    /inventory/{id}
POST   /inventory
PUT    /inventory/{id}
DELETE /inventory/{id}
```

## Sales

```text
GET  /sales
GET  /sales/{id}
POST /sales
```

## Expenses

```text
GET    /expenses
GET    /expenses/{id}
POST   /expenses
PUT    /expenses/{id}
DELETE /expenses/{id}
```

## AI

```text
POST /ai/analyze
POST /ai/recommendation
GET  /ai/reports
```

---

# AI Integration

Google Gemini is used as the selected AI platform for the project.

The purpose of AI integration is to analyse actual business information and provide useful decision-support information.

The AI module is designed to analyse:

- Sales performance
- Expense patterns
- Inventory status
- Product performance

The expected AI outputs include:

- Business performance summaries
- Sales insights
- Expense insights
- Inventory recommendations
- Potential business risks
- Actionable recommendations

---

# AI Workflow

```text
Sales + Expenses + Inventory
            ↓
     Business Data Layer
            ↓
      WordPress Backend
            ↓
       Prompt Creation
            ↓
        Gemini API
            ↓
        AI Response
            ↓
    Response Validation
            ↓
      AI Report Storage
            ↓
       AI Insights Page
```

The Gemini API key is handled by the backend and should never be exposed directly to frontend JavaScript.

---

# Prompt Engineering

A structured prompt strategy is used to help the AI produce business-focused responses.

## Example System Prompt

```text
You are an AI business management assistant.

Analyse the provided business information and generate clear,
practical and easy-to-understand business insights.

Focus on sales, expenses, inventory, product performance,
business risks and actionable recommendations.

Do not invent information that is not supported by the
provided business data.
```

## Expected Output

The AI is expected to provide:

1. Business performance summary
2. Sales observations
3. Expense observations
4. Inventory concerns
5. Business risks
6. Actionable recommendations

---

# Error Handling

The system is designed to handle common application errors such as:

- Missing required fields
- Invalid data
- Invalid product IDs
- Invalid quantities
- Insufficient inventory
- Authentication failures
- Unauthorised requests
- Database errors
- Invalid API requests
- AI API errors
- Empty AI responses
- Network failures
- AI service timeouts

Meaningful error responses are returned instead of allowing errors to crash the entire application.

---

# Development Roadmap

| Week | Major Module | Deliverables |
|------|--------------|--------------|
| Week 06 | Frontend Development | UI implementation and dashboard design |
| Week 07 | Backend Development | Database setup and API implementation |
| Week 08 | AI Integration | Gemini integration and recommendation features |
| Week 09 | Feature Completion | Finalisation of core business modules |
| Week 10 | Testing and Debugging | System testing, bug fixing and optimisation |
| Week 11 | Deployment | Final documentation and deployment |

---

# Team Task Distribution

| Team Member | Responsibilities |
|-------------|------------------|
| Imran Kabir (Group Leader) | Project coordination, backend development, AI integration and deployment |
| Sajal Biswas | Frontend development and dashboard implementation |
| Borhan Kabir | Database design, API development and AI data processing |
| Neeaj Morshed | UI/UX design, testing, debugging and documentation |

---

# Current Development Status

## Completed / Implemented

- Project proposal
- Team information
- Project structure
- System architecture
- Activity diagram
- User flow design
- Wireframes/UI planning
- Database architecture
- WordPress plugin foundation
- Authentication architecture
- Inventory module
- Sales module
- Expense module
- Dashboard backend/data layer
- REST API structure
- AI integration architecture
- Plugin source uploaded to GitHub

## Currently Being Tested / Improved

- Gemini API connectivity
- AI analysis generation
- AI recommendation generation
- AI error handling
- Frontend/backend integration
- Complete functional testing
- Security testing
- Final deployment

---

# Installation

1. Download or prepare the plugin ZIP.
2. Log in to the WordPress administration dashboard.
3. Go to **Plugins → Add New Plugin**.
4. Select **Upload Plugin**.
5. Upload the plugin ZIP file.
6. Click **Install Now**.
7. Activate the plugin.
8. Open **SaaS Business Management** from the WordPress admin menu.
9. Configure the required settings.
10. Configure the Gemini API if AI functionality is required.

---

# Project Progress

The project has progressed from the initial planning and system-design stages into functional plugin development.

The core business-management modules have been implemented, while AI integration, testing, debugging and final deployment are continuing.

The project will continue to be improved during the remaining development phases.

---

# References

1. WordPress Developer Documentation  
   https://developer.wordpress.org/

2. Google Gemini API Documentation  
   https://ai.google.dev/

3. Pressman, R. S., & Maxim, B. R.  
   *Software Engineering: A Practitioner's Approach*, 9th Edition, McGraw-Hill Education, 2019.

---

## Academic Project

This project is being developed as part of the **CSE4104** course by:

**CSE4104-7B-T03 — Stack Builders**
