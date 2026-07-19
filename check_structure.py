import re

with open('resources/views/dashboard.blade.php', 'r') as f:
    lines = f.readlines()

# Track active tags and their line numbers
stack = []
for i, line in enumerate(lines):
    line_num = i + 1
    # Simple regex to find ids of divs
    match_div_open = re.search(r'<div\s+([^>]*id="([^"]+)"[^>]*)>', line)
    match_any_div_open = re.search(r'<div\b', line)
    match_div_close = re.search(r'</div\b', line)
    
    # We will trace tag hierarchy manually
    # For a robust check, let's look for specific ids: section-transactions, sub-section-cash-advances, section-ledger
    if 'id="section-transactions"' in line:
        print(f"L{line_num}: OPEN section-transactions")
    if 'id="sub-section-cash-advances"' in line:
        print(f"L{line_num}: OPEN sub-section-cash-advances")
    if 'id="section-ledger"' in line:
        print(f"L{line_num}: OPEN section-ledger")

# Let's count standard div nesting to see where section-transactions closes
depth = 0
in_tx = False
for i, line in enumerate(lines):
    line_num = i + 1
    # Find all div opens and closes in line
    opens = list(re.finditer(r'<div\b', line))
    closes = list(re.finditer(r'</div\b', line))
    
    for op in opens:
        if 'id="section-transactions"' in line:
            in_tx = True
            depth = 0
            print(f"L{line_num}: section-transactions starts here")
        elif in_tx:
            depth += 1
            
    for cl in closes:
        if in_tx:
            if depth == 0:
                print(f"L{line_num}: section-transactions CLOSES here")
                in_tx = False
            else:
                depth -= 1
