# Known Issues & Future Improvements

## Summary

### Snapshot Format Compatibility

When upgrading the package between versions that improve database adapters, you may need to recreate snapshots to avoid false positives in `migrate:diff`.

**After upgrading:**
```bash
# Create a fresh snapshot
php artisan migrate:snapshot
```

**Upcoming improvements (next PR):**
- Snapshot format version detection with automatic warnings
- Type normalization layer to reduce false positives

This is a low-impact issue with a simple workaround. We're implementing proper version detection in the next release.

---

## Detailed Technical Information

### Snapshot Format Versioning Issue

### Issue Description

When database adapters are improved to extract more metadata (e.g., auto_increment flags, numeric precision), old snapshots created before these improvements may cause false positives in `migrate:diff`.

**Example scenario:**
1. User creates snapshot with version 1.0.0 of the package
2. User upgrades to version 1.1.0 which improves PostgreSQL adapter
3. Running `migrate:diff` shows many false positives (columns appearing as "modified" when nothing changed)

### Why This Happens

The SchemaComparator performs literal string comparisons of column metadata. When the adapter format changes:
- Old snapshot: `['type' => 'bigint', 'auto_increment' => false]` (missing flag)
- Current DB: `['type' => 'bigint', 'auto_increment' => true]` (with flag)
- Result: False positive - column appears modified

### Current Workaround

After upgrading the package, recreate snapshots:

```bash
# Delete old snapshots (optional)
php artisan migrate:snapshot --list
# Then create fresh snapshot
php artisan migrate:snapshot
```

### Planned Solutions

#### Medium-term (Next PR)

**Option 2: Snapshot Format Version Detection**

Implementation plan:
- Add `format_version` field to snapshot metadata
- Detect format mismatches when loading snapshots
- Display clear warning to users:
  ```
  ⚠️  Warning: Snapshot format mismatch detected

  Snapshot was created with format v1, but current adapter uses format v2.
  This may cause false positives in diff detection.

  Recommended: Create a new snapshot with:
  php artisan migrate:snapshot
  ```
- Optionally: `--ignore-version-mismatch` flag to bypass warning

**Option 3: Type Normalization Layer**

Implementation plan:
- Create `TypeNormalizer` class that understands equivalent types
- Normalize types before comparison:
  ```php
  // PostgreSQL variations
  'character varying(255)' -> 'varchar(255)'
  'bigint with nextval()' -> 'bigserial'

  // MySQL variations
  'int unsigned' -> 'unsigned_integer'

  // Cross-database equivalents
  'varchar(255)' == 'character varying(255)'
  'integer' == 'int'
  ```
- Smart comparison that ignores cosmetic differences
- Reduces false positives from minor adapter format changes

#### Long-term Enhancements

**Option 4: Snapshot Migration System**
- Auto-migrate old snapshot formats to new formats
- Similar to database migrations
- Preserves historical snapshots

**Option 5: Schema Diff Algorithm Improvements**
- Semantic comparison instead of literal string matching
- Understand database-specific type equivalents
- Fuzzy matching for similar types

## False Positive Prevention Checklist

When improving database adapters:

- [ ] Increment snapshot format version
- [ ] Add format version detection
- [ ] Test with old snapshots to verify warnings
- [ ] Document breaking changes in CHANGELOG
- [ ] Update migration guide with recreate-snapshot instructions

## Impact Assessment

**Current Impact:** Low
- Only affects users who upgrade package between adapter improvements
- Simple workaround (recreate snapshot)
- Does not affect data integrity or migration safety

**Priority:** Medium
- Should be addressed before v1.0.0 release
- Not blocking for MVP features

## Related Issues

- PostgreSQL adapter improvements (v0.3.x) - Added auto_increment detection
- MySQL ENUM extraction - May trigger similar issues
- SQLite type mapping - Different type system may need normalization

---

*Last Updated: 2025-10-05*
*Status: Planning for next PR*
