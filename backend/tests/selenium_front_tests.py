#!/usr/bin/env python3
"""
Selenium Frontend QA Tests for pub-ads-mar
Requires: pip install selenium
Requires: Chrome browser installed
Requires: Backend running on http://localhost:8000
Requires: Frontend running on http://localhost:4200
Requires: Fresh DB seed (php artisan migrate:fresh --seed)
"""

import time
import sys
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options

BASE = "http://localhost:4200"
WAIT = 10  # seconds

# Demo users from DatabaseSeeder (password: "password")
USERS = {
    "admin":    {"email": "admin@pubads.test",     "name": "Admin User"},
    "support":  {"email": "support@pubads.test",   "name": "Support Agent"},
    "payments": {"email": "payments@pubads.test",   "name": "Payments Officer"},
    "provider": {"email": "provider1@pubads.test",  "name": "Jean-Pierre Dupont"},
    "client":   {"email": "client1@pubads.test",    "name": "Carlos Mendez"},
}

passed = 0
failed = 0
failures = []


def log_pass(desc):
    global passed
    passed += 1
    print(f"  PASS {desc}")


def log_fail(desc, reason=""):
    global failed
    failed += 1
    tag = f"  FAIL {desc}"
    if reason:
        tag += f" -- {reason}"
    print(tag)
    failures.append(tag)


def wait_for(driver, by, value, timeout=WAIT):
    return WebDriverWait(driver, timeout).until(
        EC.presence_of_element_located((by, value))
    )


def wait_clickable(driver, by, value, timeout=WAIT):
    return WebDriverWait(driver, timeout).until(
        EC.element_to_be_clickable((by, value))
    )


def wait_url_contains(driver, fragment, timeout=WAIT):
    WebDriverWait(driver, timeout).until(EC.url_contains(fragment))


def safe_find(driver, by, value, timeout=WAIT):
    try:
        return wait_for(driver, by, value, timeout)
    except Exception:
        return None


def ensure_on_app(driver):
    """Make sure we're on the app origin (not data: or about:blank)."""
    if not driver.current_url.startswith(BASE):
        driver.get(f"{BASE}/login")
        time.sleep(2)


def login(driver, role):
    """Log in as the given role via direct API call + localStorage. Returns True on success."""
    user = USERS[role]

    # Ensure we're on the app origin before accessing localStorage
    ensure_on_app(driver)

    # Call login API via fetch, store token, and reload to /dashboard
    result = driver.execute_script("""
        // Clear old session
        localStorage.clear();
        sessionStorage.clear();

        // Synchronous XHR to login API
        var xhr = new XMLHttpRequest();
        xhr.open('POST', 'http://localhost:8000/api/login', false);
        xhr.setRequestHeader('Content-Type', 'application/json');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.send(JSON.stringify({email: arguments[0], password: 'password'}));

        if (xhr.status === 200) {
            var data = JSON.parse(xhr.responseText);
            localStorage.setItem('auth_token', data.token);
            return 'ok';
        } else {
            return 'error:' + xhr.status + ':' + xhr.responseText.substring(0, 200);
        }
    """, user["email"])

    if result != "ok":
        print(f"    [DEBUG login] API error for {user['email']}: {result}")
        return False

    # Navigate to /dashboard with the token in localStorage
    driver.get(f"{BASE}/dashboard")
    time.sleep(2)

    if "/dashboard" in driver.current_url:
        return True
    else:
        print(f"    [DEBUG login] After API login, URL: {driver.current_url}")
        return False


def navigate_to(driver, path):
    """Navigate to a path using Angular router (avoids full page reload that kills auth state)."""
    # Use Angular router navigation via URL hash/pushstate instead of driver.get
    driver.execute_script(f"window.location.href = '{BASE}{path}';")
    time.sleep(2)


def logout_via_navbar(driver):
    """Log out via navbar dropdown. Returns True on success."""
    try:
        avatar = wait_clickable(driver, By.CSS_SELECTOR, ".avatar-btn", timeout=5)
        avatar.click()
        time.sleep(0.5)

        sign_out = wait_clickable(driver, By.CSS_SELECTOR, "button.dropdown-item", timeout=5)
        sign_out.click()

        wait_url_contains(driver, "/login", timeout=WAIT)
        time.sleep(0.5)
        return True
    except Exception:
        return False


def check_page_loaded(driver, title_keyword, timeout=5):
    """Wait for an h1 containing the keyword. Returns the h1 element or None."""
    try:
        h1 = wait_for(driver, By.CSS_SELECTOR, "h1", timeout=timeout)
        if h1 and title_keyword.lower() in h1.text.lower():
            return h1
        # Try waiting a bit more for Angular to re-render
        time.sleep(1)
        h1 = driver.find_element(By.CSS_SELECTOR, "h1")
        if title_keyword.lower() in h1.text.lower():
            return h1
        return None
    except Exception:
        return None


# ── Test Suites ──────────────────────────────────────────────

def test_auth(driver):
    """Test login page, authentication, and logout."""
    print("\n--- AUTH ---")

    # 1. Login page loads
    driver.get(f"{BASE}/login")
    time.sleep(1)
    driver.execute_script("localStorage.clear(); sessionStorage.clear();")
    driver.get(f"{BASE}/login")
    time.sleep(2)

    el = safe_find(driver, By.ID, "email")
    if el:
        log_pass("Login page loads with email input")
    else:
        log_fail("Login page loads with email input", "email input not found")
        return

    # 2. Wrong credentials show error
    el.clear()
    el.send_keys("bad@email.com")
    driver.find_element(By.ID, "password").send_keys("wrong")
    driver.find_element(By.CSS_SELECTOR, "button[type='submit']").click()
    time.sleep(3)
    err = safe_find(driver, By.CSS_SELECTOR, ".alert.alert-error", timeout=5)
    if err:
        log_pass("Wrong credentials show error message")
    else:
        log_fail("Wrong credentials show error message", "no error alert found")

    # 3. Valid login redirects to dashboard
    if login(driver, "client"):
        log_pass("Client login redirects to /dashboard")
    else:
        log_fail("Client login redirects to /dashboard", f"URL: {driver.current_url}")
        return

    # 4. Dashboard shows user name
    h1 = safe_find(driver, By.CSS_SELECTOR, "h1")
    if h1 and USERS["client"]["name"].split()[0] in h1.text:
        log_pass("Dashboard shows user name")
    else:
        log_fail("Dashboard shows user name", f"h1 text: {h1.text if h1 else 'not found'}")

    # 5. Navbar shows role badge
    badge = safe_find(driver, By.CSS_SELECTOR, ".role-badge")
    if badge:
        log_pass("Navbar shows role badge")
    else:
        log_fail("Navbar shows role badge")

    # 6. Logout works via navbar
    if logout_via_navbar(driver):
        log_pass("Logout via navbar redirects to /login")
    else:
        log_fail("Logout via navbar redirects to /login")


def test_register(driver):
    """Test registration page."""
    print("\n--- REGISTER ---")

    ensure_on_app(driver)
    driver.execute_script("localStorage.clear(); sessionStorage.clear();")
    driver.get(f"{BASE}/register")
    time.sleep(2)

    # 1. Register page loads
    name_input = safe_find(driver, By.ID, "name")
    if name_input:
        log_pass("Register page loads")
    else:
        log_fail("Register page loads", "name input not found")
        return

    # 2. Role selector visible
    role_btns = driver.find_elements(By.CSS_SELECTOR, ".role-btn")
    if len(role_btns) >= 2:
        log_pass("Role selector buttons visible (client/provider)")
    else:
        log_fail("Role selector buttons visible", f"found {len(role_btns)} buttons")

    # 3. Fill form and register
    ts = str(int(time.time()))[-6:]
    name_input.clear()
    name_input.send_keys(f"Test Selenium {ts}")
    driver.find_element(By.ID, "email").send_keys(f"selenium{ts}@test.local")
    driver.find_element(By.ID, "password").send_keys("password123")
    driver.find_element(By.ID, "passwordConfirmation").send_keys("password123")

    submit = driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
    submit.click()
    try:
        wait_url_contains(driver, "/dashboard", timeout=WAIT)
        log_pass("Registration redirects to /dashboard")
    except Exception:
        # Check for error message
        err = safe_find(driver, By.CSS_SELECTOR, ".alert.alert-error", timeout=3)
        log_fail("Registration redirects to /dashboard",
                 f"Error: {err.text}" if err else f"URL: {driver.current_url}")


def test_guard_redirect(driver):
    """Test route guards redirect unauthenticated users."""
    print("\n--- GUARDS ---")

    ensure_on_app(driver)
    driver.execute_script("localStorage.clear(); sessionStorage.clear();")
    driver.get(f"{BASE}/dashboard")
    time.sleep(2)

    if "/login" in driver.current_url:
        log_pass("Unauthenticated /dashboard redirects to /login")
    else:
        log_fail("Unauthenticated /dashboard redirects to /login", f"URL: {driver.current_url}")


def test_role_pages(driver):
    """Test that each role's main pages load correctly."""
    print("\n--- CLIENT PAGES ---")
    if not login(driver, "client"):
        log_fail("Client login for page tests")
        return

    # Navigate using window.location to avoid full page reload
    pages = [
        ("/client/campaigns", "Campaign"),
        ("/client/spaces", "Space"),
        ("/client/bookings", "Booking"),
        ("/client/invoices", "Invoice"),
        ("/conversations", "Conversation"),
        ("/tickets", "Ticket"),
    ]
    for path, keyword in pages:
        navigate_to(driver, path)
        h1 = check_page_loaded(driver, keyword)
        if h1:
            log_pass(f"GET {path} -> {keyword} page loads")
        else:
            # Check if we got redirected to login
            actual_h1 = safe_find(driver, By.CSS_SELECTOR, "h1", timeout=3)
            actual_text = actual_h1.text if actual_h1 else "no h1"
            log_fail(f"GET {path} -> {keyword} page loads", f"h1: {actual_text}, URL: {driver.current_url}")

    print("\n--- PROVIDER PAGES ---")
    if not login(driver, "provider"):
        log_fail("Provider login for page tests")
        return

    pages = [
        ("/provider/spaces", "Space"),
        ("/provider/bookings", "Booking"),
        ("/provider/proofs", "Proof"),
    ]
    for path, keyword in pages:
        navigate_to(driver, path)
        h1 = check_page_loaded(driver, keyword)
        if h1:
            log_pass(f"GET {path} -> {keyword} page loads")
        else:
            actual_h1 = safe_find(driver, By.CSS_SELECTOR, "h1", timeout=3)
            actual_text = actual_h1.text if actual_h1 else "no h1"
            log_fail(f"GET {path} -> {keyword} page loads", f"h1: {actual_text}, URL: {driver.current_url}")

    print("\n--- ADMIN PAGES ---")
    if not login(driver, "admin"):
        log_fail("Admin login for page tests")
        return

    pages = [
        ("/admin/users", "User"),
        ("/admin/permissions", "Permission"),
    ]
    for path, keyword in pages:
        navigate_to(driver, path)
        h1 = check_page_loaded(driver, keyword)
        if h1:
            log_pass(f"GET {path} -> {keyword} page loads")
        else:
            actual_h1 = safe_find(driver, By.CSS_SELECTOR, "h1", timeout=3)
            actual_text = actual_h1.text if actual_h1 else "no h1"
            log_fail(f"GET {path} -> {keyword} page loads", f"h1: {actual_text}, URL: {driver.current_url}")

    print("\n--- SUPPORT PAGES ---")
    if not login(driver, "support"):
        log_fail("Support login for page tests")
        return

    navigate_to(driver, "/support/tickets")
    h1 = check_page_loaded(driver, "Ticket")
    if h1:
        log_pass("GET /support/tickets -> page loads")
    else:
        log_fail("GET /support/tickets -> page loads")

    print("\n--- PAYMENTS PAGES ---")
    if not login(driver, "payments"):
        log_fail("Payments login for page tests")
        return

    navigate_to(driver, "/payments/review")
    time.sleep(2)
    h1 = safe_find(driver, By.CSS_SELECTOR, "h1", timeout=5)
    if h1 and ("Payment" in h1.text or "Proof" in h1.text):
        log_pass("GET /payments/review -> page loads")
    else:
        log_fail("GET /payments/review -> page loads", f"h1: {h1.text if h1 else 'none'}")


def test_client_campaign_create(driver):
    """Test client can create a campaign."""
    print("\n--- CLIENT: Campaign CRUD ---")

    if not login(driver, "client"):
        log_fail("Client login for campaign test")
        return

    navigate_to(driver, "/client/campaigns")
    time.sleep(2)

    # Click New Campaign
    new_btn = None
    links = driver.find_elements(By.CSS_SELECTOR, "a")
    for link in links:
        href = link.get_attribute("href") or ""
        text = link.text or ""
        if "/campaigns/new" in href or ("New" in text and "Campaign" in text):
            new_btn = link
            break
    if new_btn:
        new_btn.click()
        time.sleep(2)
        if "/new" in driver.current_url:
            log_pass("Navigate to new campaign form")
        else:
            log_fail("Navigate to new campaign form", f"URL: {driver.current_url}")
            return
    else:
        log_fail("New Campaign button found")
        return

    # Fill campaign form
    name_input = safe_find(driver, By.ID, "name")
    if name_input:
        ts = str(int(time.time()))[-6:]
        name_input.clear()
        name_input.send_keys(f"Selenium Campaign {ts}")

        desc = driver.find_element(By.ID, "description")
        desc.clear()
        desc.send_keys("Test campaign from Selenium")

        start = driver.find_element(By.ID, "start_date")
        start.send_keys("2026-04-01")

        end = driver.find_element(By.ID, "end_date")
        end.send_keys("2026-04-30")

        budget = driver.find_element(By.ID, "budget")
        budget.clear()
        budget.send_keys("1000")

        submit = driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
        submit.click()
        time.sleep(3)

        if "/campaigns" in driver.current_url and "/new" not in driver.current_url:
            log_pass("Create campaign submits successfully")
        else:
            log_fail("Create campaign submits successfully", f"URL: {driver.current_url}")
    else:
        log_fail("Campaign form has name input")


def test_provider_space_create(driver):
    """Test provider can create a space."""
    print("\n--- PROVIDER: Space CRUD ---")

    if not login(driver, "provider"):
        log_fail("Provider login for space test")
        return

    navigate_to(driver, "/provider/spaces")
    time.sleep(2)

    new_btn = None
    links = driver.find_elements(By.CSS_SELECTOR, "a")
    for link in links:
        href = link.get_attribute("href") or ""
        text = link.text or ""
        if "/spaces/new" in href or "Add" in text or ("New" in text and "Space" in text):
            new_btn = link
            break
    if new_btn:
        new_btn.click()
        time.sleep(2)
        if "/new" in driver.current_url:
            log_pass("Navigate to new space form")
        else:
            log_fail("Navigate to new space form", f"URL: {driver.current_url}")
            return
    else:
        log_fail("New space button found")
        return

    name_input = safe_find(driver, By.ID, "name")
    if name_input:
        ts = str(int(time.time()))[-6:]
        name_input.clear()
        name_input.send_keys(f"Selenium Space {ts}")

        lat = driver.find_element(By.ID, "latitude")
        lat.clear()
        lat.send_keys("48.8566")

        lng = driver.find_element(By.ID, "longitude")
        lng.clear()
        lng.send_keys("2.3522")

        price = driver.find_element(By.ID, "price_per_day")
        price.clear()
        price.send_keys("50")

        submit = driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
        submit.click()
        time.sleep(3)

        if "/spaces" in driver.current_url and "/new" not in driver.current_url:
            log_pass("Create space submits successfully")
        else:
            log_fail("Create space submits successfully", f"URL: {driver.current_url}")
    else:
        log_fail("Space form has name input")


def test_admin_users_table(driver):
    """Test admin users table shows data."""
    print("\n--- ADMIN: Users Table ---")

    if not login(driver, "admin"):
        log_fail("Admin login for users test")
        return

    navigate_to(driver, "/admin/users")
    time.sleep(3)

    rows = driver.find_elements(By.CSS_SELECTOR, "table tbody tr")
    if len(rows) > 0:
        log_pass(f"Admin users table shows {len(rows)} rows")
    else:
        log_fail("Admin users table has rows", "no rows found")


def test_ticket_create(driver):
    """Test creating a support ticket."""
    print("\n--- SHARED: Ticket Creation ---")

    if not login(driver, "client"):
        log_fail("Client login for ticket test")
        return

    navigate_to(driver, "/tickets")
    time.sleep(2)

    new_btn = None
    links = driver.find_elements(By.CSS_SELECTOR, "a")
    for link in links:
        href = link.get_attribute("href") or ""
        text = link.text or ""
        if "/tickets/new" in href or "New" in text:
            new_btn = link
            break
    if new_btn:
        new_btn.click()
        time.sleep(2)

        subject = safe_find(driver, By.ID, "subject")
        if subject:
            ts = str(int(time.time()))[-6:]
            subject.clear()
            subject.send_keys(f"Selenium Ticket {ts}")

            body = safe_find(driver, By.ID, "body", timeout=3)
            if not body:
                body = safe_find(driver, By.CSS_SELECTOR, "textarea", timeout=3)
            if body:
                body.clear()
                body.send_keys("Test ticket from Selenium.")

            submit = driver.find_element(By.CSS_SELECTOR, "button[type='submit']")
            submit.click()
            time.sleep(3)

            if "/tickets" in driver.current_url and "/new" not in driver.current_url:
                log_pass("Create ticket submits successfully")
            else:
                log_fail("Create ticket submits successfully", f"URL: {driver.current_url}")
        else:
            log_fail("Ticket form has subject input")
    else:
        log_fail("New ticket button found")


def test_sidebar_and_layout(driver):
    """Test sidebar navigation and layout components."""
    print("\n--- LAYOUT & NAVIGATION ---")

    if not login(driver, "client"):
        log_fail("Client login for layout test")
        return

    driver.set_window_size(1280, 900)
    time.sleep(0.5)

    # Check sidebar exists
    sidebar = safe_find(driver, By.CSS_SELECTOR, "aside.sidebar", timeout=5)
    if sidebar:
        log_pass("Sidebar present in layout")
    else:
        log_fail("Sidebar present in layout")

    # Check navbar exists
    navbar = safe_find(driver, By.CSS_SELECTOR, "nav.navbar", timeout=3)
    if navbar:
        log_pass("Navbar present in layout")
    else:
        log_fail("Navbar present in layout")

    # Check sidebar nav items
    nav_items = driver.find_elements(By.CSS_SELECTOR, ".sidebar-nav .nav-item")
    if len(nav_items) >= 3:
        log_pass(f"Client sidebar has {len(nav_items)} nav items")
    else:
        log_fail(f"Client sidebar has nav items", f"found {len(nav_items)}")

    # Click a sidebar link and verify navigation
    if len(nav_items) >= 2:
        target = nav_items[1]
        target_text = target.text.strip()
        target.click()
        time.sleep(2)
        if "/login" not in driver.current_url:
            log_pass(f"Sidebar link '{target_text}' navigates correctly")
        else:
            log_fail(f"Sidebar link navigates", "redirected to login")


# ── Main ─────────────────────────────────────────────────────

def main():
    print("=" * 60)
    print("PUB-ADS-MAR SELENIUM FRONTEND TESTS")
    print("=" * 60)

    headless = "--headless" in sys.argv

    options = Options()
    if headless:
        options.add_argument("--headless=new")
    options.add_argument("--window-size=1280,900")
    options.add_argument("--disable-gpu")
    options.add_argument("--no-sandbox")

    driver = webdriver.Chrome(options=options)
    driver.implicitly_wait(2)

    try:
        test_auth(driver)
        test_register(driver)
        test_guard_redirect(driver)
        test_role_pages(driver)
        test_client_campaign_create(driver)
        test_provider_space_create(driver)
        test_admin_users_table(driver)
        test_ticket_create(driver)
        test_sidebar_and_layout(driver)
    except Exception as e:
        print(f"\n  FATAL: {e}")
        import traceback
        traceback.print_exc()
        log_fail("Unhandled exception", str(e))
    finally:
        driver.quit()

    print(f"\n{'=' * 60}")
    print(f"RESULTS: {passed} passed, {failed} failed, {passed + failed} total")
    print(f"{'=' * 60}")

    if failures:
        print("\nFailed tests:")
        for f in failures:
            print(f"  - {f.strip()}")

    sys.exit(1 if failed > 0 else 0)


if __name__ == "__main__":
    main()
