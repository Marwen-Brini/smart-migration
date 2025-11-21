# Web Dashboard Architecture

## Overview

Building a Vue 3-based web dashboard for Smart Migration package that provides real-time visibility into migration status, schema structure, and performance metrics.

## Tech Stack

- **Frontend:** Vue 3 (Composition API)
- **Build Tool:** Vite
- **Styling:** Tailwind CSS
- **Charts:** Chart.js / Recharts
- **HTTP Client:** Axios
- **Real-time:** Laravel Echo + Reverb (optional for v2.1)

## Directory Structure

```
smart-migration/
├── resources/
│   ├── js/
│   │   ├── app.js                    # Vue app entry point
│   │   ├── components/
│   │   │   ├── Dashboard.vue         # Main dashboard view
│   │   │   ├── MigrationStatus.vue   # Status overview card
│   │   │   ├── SchemaExplorer.vue    # Interactive schema browser
│   │   │   ├── MigrationTimeline.vue # History timeline
│   │   │   ├── MetricsPanel.vue      # Performance charts
│   │   │   ├── TableCard.vue         # Table details component
│   │   │   ├── DriftAlert.vue        # Schema drift warnings
│   │   │   └── SnapshotList.vue      # Snapshot browser
│   │   ├── composables/
│   │   │   ├── useMigrations.js      # Migration data fetching
│   │   │   ├── useSchema.js          # Schema data fetching
│   │   │   └── useMetrics.js         # Metrics data fetching
│   │   └── utils/
│   │       ├── api.js                # API client
│   │       └── formatters.js         # Data formatters
│   ├── css/
│   │   └── app.css                   # Tailwind + custom styles
│   └── views/
│       └── dashboard.blade.php       # Main dashboard view
├── src/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   └── DashboardController.php      # Serve dashboard view
│   │   └── Api/
│   │       └── DashboardApiController.php   # API endpoints
│   ├── Dashboard/
│   │   ├── DashboardService.php      # Business logic
│   │   └── MetricsCollector.php      # Metrics aggregation
│   └── Commands/
│       └── UICommand.php             # Launch dashboard server
├── routes/
│   └── dashboard.php                 # Dashboard routes
├── vite.config.js                    # Vite configuration
├── tailwind.config.js                # Tailwind configuration
└── package.json                      # NPM dependencies
```

## API Endpoints

### Status & Overview
- `GET /api/smart-migration/status`
  - Overall migration status (pending count, applied count, drift detected, etc.)
  - Environment info (Laravel version, database driver, etc.)

### Migrations
- `GET /api/smart-migration/migrations`
  - List all migrations (pending and applied)
  - Include risk assessment and time estimates

- `GET /api/smart-migration/migrations/{migration}`
  - Detailed migration info with SQL preview

### History
- `GET /api/smart-migration/history`
  - Timeline of applied migrations with timestamps
  - Author info (if tracked)
  - Performance data (execution time)

### Schema
- `GET /api/smart-migration/schema`
  - Current database schema structure
  - Tables, columns, indexes, foreign keys
  - Row counts per table

- `GET /api/smart-migration/schema/table/{table}`
  - Detailed table structure
  - Column definitions, indexes, constraints

### Drift Detection
- `GET /api/smart-migration/drift`
  - Current drift status
  - Differences between migrations and database

### Snapshots
- `GET /api/smart-migration/snapshots`
  - List of all snapshots

- `GET /api/smart-migration/snapshots/{snapshot}`
  - Snapshot details

### Metrics
- `GET /api/smart-migration/metrics`
  - Performance metrics
  - Migration execution times
  - Archive sizes
  - Database statistics

## Vue Components Breakdown

### 1. Dashboard.vue (Main View)
- Grid layout with overview cards
- Navigation between sections
- Real-time status updates

### 2. MigrationStatus.vue
- Pending migrations count
- Last migration applied
- Drift warnings
- Quick actions (run plan, check drift)

### 3. SchemaExplorer.vue
- Tree view of database structure
- Search and filter tables
- Click to view table details
- Visual relationship diagram (future)

### 4. MigrationTimeline.vue
- Chronological list of applied migrations
- Filter by date range
- Show execution time and status
- Expandable details

### 5. MetricsPanel.vue
- Charts for:
  - Migration execution times (line chart)
  - Risk distribution (pie chart)
  - Archive sizes over time (bar chart)
  - Database table sizes (bar chart)

### 6. TableCard.vue
- Table structure details
- Column list with types
- Indexes and foreign keys
- Row count
- Recent changes

### 7. DriftAlert.vue
- Warning banner when drift detected
- List of differences
- "Generate fix migration" button

### 8. SnapshotList.vue
- List of snapshots with dates
- Compare snapshots
- Create new snapshot button

## Implementation Phases

### Phase 1: Basic Setup (Week 1)
- [ ] Set up Vue 3 + Vite + Tailwind
- [ ] Create basic API endpoints
- [ ] Build main dashboard layout
- [ ] Create DashboardService for data aggregation

### Phase 2: Core Features (Week 2)
- [ ] MigrationStatus component
- [ ] MigrationTimeline component
- [ ] Basic schema explorer
- [ ] API integration with real data

### Phase 3: Advanced Features (Week 3)
- [ ] MetricsPanel with charts
- [ ] Drift detection display
- [ ] Snapshot management
- [ ] Responsive design polish

### Phase 4: Polish & Testing (Week 4)
- [ ] Error handling and loading states
- [ ] Unit tests for Vue components
- [ ] API tests
- [ ] Documentation
- [ ] Demo data seeder

## Command Usage

```bash
# Start dashboard server
php artisan migrate:ui

# Output:
# ✅ Smart Migration Dashboard running at: http://localhost:8080
# Press Ctrl+C to stop.
```

## Configuration

Add to `config/smart-migration.php`:

```php
'dashboard' => [
    'enabled' => true,
    'port' => 8080,
    'host' => 'localhost',
    'auth' => [
        'enabled' => false, // Enable for production
        'middleware' => ['auth'], // Laravel auth middleware
    ],
    'metrics' => [
        'retention_days' => 90,
        'sample_rate' => 1.0,
    ],
],
```

## Security Considerations

1. **Authentication:** Optional auth middleware for production
2. **CORS:** Configure CORS for API endpoints
3. **Rate Limiting:** Apply rate limits to API endpoints
4. **Input Validation:** Validate all API inputs
5. **Read-Only:** Dashboard is read-only (no destructive operations)

## Future Enhancements (v2.1+)

- Real-time updates via WebSockets
- Drag-and-drop migration ordering
- Manual migration execution from dashboard
- Team collaboration (comments, approvals)
- Export reports (PDF, CSV)
- Custom metrics and alerts
- Dark mode theme

## Notes

- Dashboard will be read-only in v2.0 (no mutation operations)
- Focus on visibility and monitoring
- Ensure 100% test coverage
- Mobile-responsive design
- Accessible (WCAG 2.1 AA)
