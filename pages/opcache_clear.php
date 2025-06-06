<?php
if (function_exists('opcache_reset')) {
  opcache_reset();
  echo "OpCache cleared.";
} else {
  echo "opcache_reset() is not available.";
}