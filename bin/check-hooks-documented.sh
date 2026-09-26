#!/usr/bin/env bash
# Gate: wbam_ hooks must have a docblock summary + one @param per argument,
# and audit/manifest.json's hooks inventory must match the source.
#
# Wire into CI/release as: bash bin/check-hooks-documented.sh
set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."
php bin/check-hooks-documented.php
