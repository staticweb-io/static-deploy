#!/usr/bin/env bash

set -euo pipefail

nix shell .#wordpress-firecracker -c microvm-run
