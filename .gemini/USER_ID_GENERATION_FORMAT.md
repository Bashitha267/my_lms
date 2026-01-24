# ✅ User ID Generation - Prefix-Based Format

## 🎯 Current System (Confirmed)

Both `register.php` and `admin/add_user.php` now use the **same prefix-based format** for generating user IDs.

---

## 📋 User ID Format

### Format Structure
```
{prefix}_{number}
```

### Role Prefixes
| Role | Prefix | Example IDs |
|------|--------|-------------|
| **Student** | `stu` | stu_1000, stu_1001, stu_1002... |
| **Teacher** | `tea` | tea_1000, tea_1001, tea_1002... |
| **Instructor** | `ins` | ins_1000, ins_1001, ins_1002... |
| **Admin** | `adm` | adm_1000, adm_1001, adm_1002... |

### Number Format
- **Starts from:** 1000
- **Format:** 4-digit zero-padded (e.g., 1000, 1001...9999)
- **Pattern:** `{prefix}_{NNNN}`

---

## 🔧 Implementation Details

### Code Logic (Both Files)

```php
// 1. Define role prefixes
$role_prefix = [
    'student' => 'stu',
    'teacher' => 'tea',
    'instructor' => 'ins',
    'admin' => 'adm'
];
$prefix = $role_prefix[$role] ?? 'usr';

// 2. Query for last ID with this prefix
$stmt = $conn->prepare("SELECT user_id FROM users WHERE user_id LIKE ? ORDER BY user_id DESC LIMIT 1");
$pattern = $prefix . '_%';
$stmt->bind_param("s", $pattern);
$stmt->execute();
$result = $stmt->get_result();

// 3. Calculate next number
$next_num = 1000; // Start from 1000
if ($result->num_rows > 0) {
    $last_user = $result->fetch_assoc();
    $last_num = intval(substr($last_user['user_id'], strlen($prefix) + 1));
    $next_num = max($last_num + 1, 1000);
}

// 4. Format final user_id
$user_id = $prefix . '_' . str_pad($next_num, 4, '0', STR_PAD_LEFT);
// Result: stu_1000, stu_1001, tea_2000, etc.
```

---

## 📁 Files Using This System

### 1. `register.php`
- **Purpose:** Student registration
- **Default Role:** student
- **Generated IDs:** stu_1000, stu_1001, stu_1002...
- **Display:** Shows next available ID before submission

### 2. `admin/add_user.php`
- **Purpose:** Admin creates users of any role
- **Roles:** student, teacher, instructor, admin
- **Generated IDs:** Depends on selected role
- **Display:** Shows format examples and next ID pattern

---

## 🎨 User Display Examples

### Registration Page (register.php)
```
┌─────────────────────────────────────┐
│ ℹ️ Your User ID will be:           │
│                                     │
│ User ID: stu_1000                   │
│                                     │
│ (Auto-generated)                    │
└─────────────────────────────────────┘
```

### Admin Add User Page
```
┌─────────────────────────────────────────────────────────┐
│ ℹ️ Next User ID (will be auto-generated):              │
│                                                          │
│ stu_1000                                                 │
│ (Format: prefix_number, starts from 1000)                │
│                                                          │
│ student: stu_XXXX | teacher: tea_XXXX |                  │
│ instructor: ins_XXXX | admin: adm_XXXX                   │
└─────────────────────────────────────────────────────────┘
```

---

## 📊 Examples

### First Users of Each Role
```
stu_1000  // First student
tea_1000  // First teacher
ins_1000  // First instructor
adm_1000  // First admin
```

### After Multiple Users
```
stu_1000  // 1st student
stu_1001  // 2nd student
stu_1002  // 3rd student
...
tea_1000  // 1st teacher
tea_1001  // 2nd teacher
...
```

### Maximum Capacity
```
stu_9999  // Last possible student ID
tea_9999  // Last possible teacher ID
ins_9999  // Last possible instructor ID
adm_9999  // Last possible admin ID
```

**Total Capacity:** 9000 users per role (1000-9999)

---

## ✅ Consistency Check

### Both Files Match
- ✅ Same prefix mapping
- ✅ Same starting number (1000)
- ✅ Same query logic
- ✅ Same format pattern
- ✅ Same number padding (4 digits)

### Database Column
```sql
user_id VARCHAR(20) -- Supports format like 'stu_1000'
```

---

## 🔍 Query Pattern

### How It Works
```sql
-- For student prefix 'stu'
SELECT user_id FROM users 
WHERE user_id LIKE 'stu_%' 
ORDER BY user_id DESC 
LIMIT 1

-- Returns: stu_1005 (last student)
-- PHP extracts: 1005
-- Increments to: 1006
-- Formats as: stu_1006
```

---

## 💡 Benefits

1. **Role Identification:** Instantly know role from ID
2. **Sequential:** Easy to track creation order
3. **Readable:** Human-friendly format
4. **Sortable:** Can sort by role and number
5. **Unique:** Each role has separate numbering

---

## 🎯 Summary

**Format:** `{prefix}_{number}`  
**Prefixes:** stu, tea, ins, adm  
**Starting Number:** 1000  
**Padding:** 4 digits  
**Both Files:** ✅ Synchronized  

**Examples:**
- stu_1000 (First student)
- tea_2345 (2346th teacher)
- adm_1001 (2nd admin)

---

*Last Updated: 2026-01-15 21:23 IST*  
*Status: ✅ Both files synchronized with prefix-based format*
