# KISS SBI MKII Integration - Copy Instructions

**Date**: August 29, 2025  
**Purpose**: Copy KISS SBI MKII files for Git Updater integration analysis

---

## 📁 Directory Structure Created

```
integration/
├── kiss-sbi-reference/
│   ├── src/
│   │   ├── Services/
│   │   ├── Admin/
│   │   ├── API/
│   │   └── Container/
│   ├── assets/
│   ├── dist/
│   ├── framework/
│   └── docs/
└── COPY-INSTRUCTIONS.md (this file)
```

---

## 📋 Copy Instructions

### **Step 1: Core Plugin Files**
Copy these files from KISS SBI MKII root directory:

```bash
# From KISS-Smart-Batch-Installer-MKII/ to integration/kiss-sbi-reference/

# Main plugin file
cp nhk-kiss-batch-installer.php integration/kiss-sbi-reference/

# Configuration files
cp composer.json integration/kiss-sbi-reference/
cp package.json integration/kiss-sbi-reference/
cp tsconfig.json integration/kiss-sbi-reference/

# Documentation
cp PROJECT-FSM.md integration/kiss-sbi-reference/docs/
cp FRAMEWORK.md integration/kiss-sbi-reference/docs/
cp CHANGELOG.md integration/kiss-sbi-reference/docs/
```

### **Step 2: Core Services (Priority 1)**
Copy these critical service files:

```bash
# State Management (FSM Core)
cp src/Services/StateManager.php integration/kiss-sbi-reference/src/Services/

# GitHub Integration
cp src/Services/GitHubService.php integration/kiss-sbi-reference/src/Services/

# Plugin Management
cp src/Services/PluginDetectionService.php integration/kiss-sbi-reference/src/Services/
cp src/Services/PluginInstallationService.php integration/kiss-sbi-reference/src/Services/

# PQS Integration
cp src/Services/PQSIntegration.php integration/kiss-sbi-reference/src/Services/

# Validation
cp src/Services/ValidationGuardService.php integration/kiss-sbi-reference/src/Services/
```

### **Step 3: Admin Interface (Priority 1)**
Copy the UI components:

```bash
# List Table Implementation
cp src/Admin/RepositoryListTable.php integration/kiss-sbi-reference/src/Admin/

# Repository Manager
cp src/Admin/RepositoryManager.php integration/kiss-sbi-reference/src/Admin/

# Self Tests Page
cp src/Admin/NewSelfTestsPage.php integration/kiss-sbi-reference/src/Admin/
```

### **Step 4: API Layer (Priority 1)**
Copy the AJAX and API handlers:

```bash
# AJAX Handler
cp src/API/AjaxHandler.php integration/kiss-sbi-reference/src/API/

# Any other API files
cp -r src/API/* integration/kiss-sbi-reference/src/API/
```

### **Step 5: Container & Plugin Bootstrap (Priority 1)**
Copy the dependency injection and main plugin class:

```bash
# Main Plugin Class
cp src/Plugin.php integration/kiss-sbi-reference/src/

# Container Implementation
cp src/Container.php integration/kiss-sbi-reference/src/
```

### **Step 6: Assets (Priority 2)**
Copy the frontend assets:

```bash
# CSS and JavaScript
cp -r assets/* integration/kiss-sbi-reference/assets/

# TypeScript compiled output
cp -r dist/* integration/kiss-sbi-reference/dist/
```

### **Step 7: Framework (Priority 3)**
Copy the NHK Framework if needed:

```bash
# Framework files (if they exist)
cp -r framework/* integration/kiss-sbi-reference/framework/
```

---

## 🎯 Analysis Priority

### **Immediate Analysis (Priority 1)**
1. **StateManager.php** - FSM implementation and state transitions
2. **RepositoryListTable.php** - WordPress List Table UI
3. **AjaxHandler.php** - AJAX endpoints and real-time updates
4. **Plugin.php** - Service registration and dependency injection
5. **admin.css/admin.js** - UI styling and JavaScript functionality

### **Secondary Analysis (Priority 2)**
1. **GitHubService.php** - API integration patterns
2. **PluginInstallationService.php** - Installation workflow
3. **TypeScript modules** - Frontend FSM and real-time updates
4. **Container.php** - Dependency injection implementation

### **Reference Analysis (Priority 3)**
1. **Framework files** - NHK Framework patterns
2. **Documentation** - Architecture and FSM patterns
3. **Configuration files** - Build and dependency setup

---

## 🔍 What I'll Analyze

Once files are copied, I'll examine:

### **FSM Architecture**
- How StateManager implements finite state machine
- State transition rules and validation
- Real-time broadcasting via SSE
- Integration points with WordPress

### **UI Components**
- List table implementation and customization
- AJAX integration and real-time updates
- CSS styling and responsive design
- TypeScript frontend architecture

### **Service Integration**
- Dependency injection container setup
- Service registration patterns
- GitHub API integration
- Plugin installation workflows

### **Integration Strategy**
- How to adapt FSM for Git Updater workflows
- UI component integration with existing Git Updater pages
- Asset loading and WordPress integration
- Backward compatibility considerations

---

## ✅ Next Steps

1. **Copy the files** using the instructions above
2. **Verify file structure** matches the expected layout
3. **Run analysis** on the copied files to understand architecture
4. **Create integration plan** based on actual code examination
5. **Begin implementation** starting with core FSM integration

**Note**: Focus on Priority 1 files first - these contain the core FSM and UI components that will drive the integration strategy.
