#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

PATTERNS=(
  'password\s*=\s*[^$\s{]'
  'secret\s*=\s*[^$\s{]'
  'api[_-]?key\s*=\s*[^$\s{]'
  'BEGIN (RSA |OPENSSH )?PRIVATE KEY'
  'sk_live_'
  'AKIA[0-9A-Z]{16}'
)

FAIL=0

if git ls-files --error-unmatch .env >/dev/null 2>&1; then
  echo ".env is tracked by git and must be removed from version control."
  FAIL=1
fi

for path in token.txt staff_token.txt .auth; do
  if git ls-files --error-unmatch "$path" >/dev/null 2>&1; then
    echo "Tracked local credential artifact detected: $path"
    FAIL=1
  fi
done

if git diff --cached --name-only | rg -n -i '(^|/)(token\.txt|staff_token\.txt|\.auth/)' >/dev/null 2>&1; then
  echo "Staged local credential artifacts detected."
  FAIL=1
fi

TRACKED_FILES=()
while IFS= read -r file; do
  TRACKED_FILES+=("$file")
done < <(git ls-files)

if ((${#TRACKED_FILES[@]} > 0)); then
  for pattern in "${PATTERNS[@]}"; do
    if rg -n -i --no-messages "${TRACKED_FILES[@]}" -e "$pattern" >/tmp/secret-scan.txt 2>/dev/null; then
      echo "Potential secret match ($pattern) in tracked files:"
      cat /tmp/secret-scan.txt
      FAIL=1
    fi
  done
fi

if [[ "$FAIL" -ne 0 ]]; then
  echo "Secret scan failed."
  exit 1
fi

echo "Secret scan passed."
