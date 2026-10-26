#!/usr/bin/env bash
# Installable plugin zip banao (dev files .distignore ke hisaab se nikal kar). rsync ki zaroorat nahi.
set -euo pipefail
cd "$(dirname "$0")/.."
slug="pennova-ai"
ver="$(grep -m1 -E '^ \* Version:' pennova-ai.php | awk '{print $3}')"
rm -rf build && mkdir -p "build/$slug"
shopt -s dotglob
for f in *; do
  [[ "$f" == "build" || "$f" == ".git" ]] && continue
  cp -a "$f" "build/$slug/"
done
(
  cd "build/$slug"
  while IFS= read -r pat; do
    [[ -z "$pat" || "$pat" == \#* ]] && continue
    rm -rf ./${pat#/}
  done < ../../.distignore
)
(cd build && zip -rq "$slug-$ver.zip" "$slug")
echo "Built build/$slug-$ver.zip"
