import os

path = r"C:\Users\ADMIN\Desktop\TEST\src\php\public\admin\dashboard.php"
with open(path, "r", encoding="utf-8") as f:
    content = f.read()

checks = {
    "Fix 1: System Health -> live_monitor.php": "live_monitor.php" in content,
    "Fix 2: Add Student -> ?action=add": "students.php?action=add" in content,
    "Fix 3: Edit buttons -> action=edit": "action=edit&id=" in content,
    "Fix 4: Keyboard role=button": 'role="button"' in content,
    "Fix 4: Keyboard tabindex=0": 'tabindex="0"' in content,
    "Fix 5: aria-live=polite": 'aria-live="polite"' in content,
    "Fix 6: Overflow menus": "overflow-menu" in content,
    "Keyboard onkeydown handler": "onkeydown" in content,
    "DASHBOARD HEADER preserved": "DASHBOARD HEADER" in content,
    "PLATFORM SUMMARY preserved": "PLATFORM SUMMARY" in content,
    "ANALYTICS CHARTS preserved": "ANALYTICS CHARTS" in content,
    "QUICK ACTIONS preserved": "QUICK ACTIONS" in content,
    "LIVE ACTIVITY preserved": "LIVE ACTIVITY" in content,
    "SYSTEM STATUS preserved": "SYSTEM STATUS" in content,
}

print("=== VALIDATION REPORT ===")
print()
for check, result in checks.items():
    status = "PASS" if result else "FAIL"
    print(f"  [{status}] {check}")

failures = [k for k, v in checks.items() if not v]
if failures:
    print(f"\nFAILURES: {len(failures)}")
    for f in failures:
        print(f"  - {f}")
else:
    print("\nAll checks passed!")
