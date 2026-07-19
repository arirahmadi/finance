import re

with open('resources/views/dashboard.blade.php', 'r') as f:
    content = f.read()

# We want to trace the block from line 191 (<div id="section-transactions") to line 914 (<div id="section-ledger")
# Let's find the substring from line 191 to 914
lines = content.split('\n')
target_sub = '\n'.join(lines[190:913]) # 0-indexed: line 191 is index 190, line 913 is index 912

# Count open vs close divs in this range
open_divs = len(re.findall(r'<div\b', target_sub))
close_divs = len(re.findall(r'</div\b', target_sub))

print(f"Open divs count: {open_divs}")
print(f"Close divs count: {close_divs}")
print(f"Difference (Open - Close): {open_divs - close_divs}")
