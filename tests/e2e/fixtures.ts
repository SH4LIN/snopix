/**
 * Playwright test + expect re-export.
 *
 * All e2e spec files import from here so that custom fixtures can be added
 * in one place without touching every spec.
 */
export { test, expect } from '@playwright/test';
