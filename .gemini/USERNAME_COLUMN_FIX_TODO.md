# Username Column Removal - Files to Fix

## Summary
The `username` column has been removed from the `users` table. This document lists all files that still reference this column and need to be updated.

## Files Already Fixed ✅
1. **auth.php** - Updated login to use user_id/mobile_number instead of username
2. **login.php** - Changed input field to "identifier"
3. **register.php** - Removed username from registration
4. **check_session.php** - Removed username from SELECT query
5. **admin\dashboard.php** - Removed username from teachers query
6. **get_teachers.php** - Removed username from SELECT and array  

## Files That Still Need Fixing ❌

### Critical Files (Cause Errors):

1. **admin\add_user.php**
   - Line 17: Remove `$username = trim($_POST['username'] ?? '');`
   - Line 28: Update validation to remove username check
   - Line 169-170: Remove username from INSERT query
   - Line 305: Update success message to use user_id instead
   - Lines 384-388: Remove username input field from HTML form
   - The admin add_user form should auto-generate user_id like register.php does

2. **admin\edit_user.php**
   - Line 188: Remove username from page title (use user_id or name instead)
   - Line 216: Remove username initial display
   - Needs SELECT query updated to remove username column

3. **admin\users.php**
   - Line 77: Remove username from SELECT query
   - Line 100: Remove username from search LIKE clause
   - Line 317: Remove username display in table

4. **admin\get_teachers.php**
   - Line 25: Remove username from SELECT query
   - Line 72: Remove username from array

5. **admin\update_students.php**
   - Line 140: Remove username from SELECT query
   - Line 316: Remove username display

6. **admin\verify_payments.php**
   - Lines 48, 61, 77, 88, 167: Remove username from SELECT queries
   - Lines 255, 314, 566: Remove username display in UI

7. **dashboard\dashboard.php**
   - Line 23: Remove username from SELECT query
   - Line 93: Remove username from array
   - Line 240: Change to use user_id or name instead

8. **dashboard\content.php**
   - Line 8: Change `$username = $_SESSION['username']` to use user_id or full name

9. **dashboard\payments.php**
   - Line 200: Remove username from SELECT query

### Display-Only Files (Won't Cause SQL Errors but show wrong data):

10. **admin\header.php**
    - Lines 44-46, 97-100: These check `isset($_SESSION['username'])` for display
    - Should continue working since we set`$_SESSION['username'] = $user_id` for backward compatibility
    - BUT displays user_id instead of a proper username - may want to update to show full name

11. **dashboard\navbar.php**
    - Lines 25, 75, 111, 187: Similar to header.php
    - Shows user_id instead of proper display name

## Recommended Approach:

### Option 1: Quick Fix (Maintain Compatibility)
- Since `$_SESSION['username']` now contains `user_id`, most display-only references will work
- Focus on fixing SQL queries first (Files 1-9)
- Update display later to show full names instead of user_id

### Option 2: Complete Refactor
- Fix all SQL queries to remove username column
- Update all display logic to show `first_name + second_name` instead of username
- Remove all username input fields from forms
- Update success/error messages to reference user_id

## Priority Fix List:
1. **admin\add_user.php** (users can't be created via admin panel)
2. **admin\users.php** (admin can't view user list)
3. **admin\edit_user.php** (admin can't edit users)
4. **admin\get_teachers.php** (teacher dropdowns won't load)
5. **admin\update_students.php** (student management broken)
6. **dashboard\dashboard.php** (user dashboard broken)
7. **admin\verify_payments.php** (payment verification broken)
8. All remaining files (display issues only)

## SQL Pattern to Fix:
Replace: `SELECT ... username, ... FROM users`
With: `SELECT ... FROM users` (remove username)

Replace: `'username' => $row['username']`
With: (remove this line from array)

Replace: Username input fields
With: (remove or make auto-generated like in register.php)
