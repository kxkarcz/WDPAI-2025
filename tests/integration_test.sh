#!/bin/bash
# =============================================================================
# Testy integracyjne endpointów MindGarden
# Uruchom: bash tests/integration_test.sh http://localhost:8080
# =============================================================================

BASE_URL="${1:-http://localhost:8080}"
PASSED=0
FAILED=0

# Kolory
GREEN='\033[0;32m'
RED='\033[0;31m'
NC='\033[0m' # No Color

test_endpoint() {
    local method="$1"
    local endpoint="$2"
    local expected_code="$3"
    local description="$4"
    
    response=$(curl -s -o /dev/null -w "%{http_code}" -X "$method" "${BASE_URL}${endpoint}")
    
    if [ "$response" -eq "$expected_code" ]; then
        echo -e "${GREEN}✓ PASS${NC}: $description (HTTP $response)"
        ((PASSED++))
    else
        echo -e "${RED}✗ FAIL${NC}: $description (Expected $expected_code, got $response)"
        ((FAILED++))
    fi
}

echo "=============================================="
echo "Testy integracyjne MindGarden"
echo "URL bazowy: $BASE_URL"
echo "=============================================="
echo ""

# Testy publicznych endpointów
echo "--- Endpointy publiczne ---"
test_endpoint "GET" "/" 200 "Strona główna (login)"
test_endpoint "GET" "/login" 200 "Strona logowania"
test_endpoint "GET" "/register" 200 "Strona rejestracji"

# Testy błędów
echo ""
echo "--- Obsługa błędów ---"
test_endpoint "GET" "/nieistniejaca-strona" 404 "Strona 404"
test_endpoint "GET" "/admin/dashboard" 302 "Panel admina bez autoryzacji (redirect)"
test_endpoint "GET" "/patient/dashboard" 302 "Panel pacjenta bez autoryzacji (redirect)"
test_endpoint "GET" "/psychologist/dashboard" 302 "Panel psychologa bez autoryzacji (redirect)"

# Test POST bez CSRF (powinien zwrócić błąd lub redirect)
echo ""
echo "--- Zabezpieczenia ---"
test_endpoint "POST" "/login" 403 "Login bez CSRF tokena"
test_endpoint "POST" "/register" 403 "Rejestracja bez CSRF tokena"

# Test API bez autoryzacji
echo ""
echo "--- API endpoints ---"
test_endpoint "GET" "/api/patient/moods" 302 "API moods bez autoryzacji"
test_endpoint "GET" "/api/chat/messages" 302 "API chat bez autoryzacji"

echo ""
echo "=============================================="
echo "Podsumowanie: ${GREEN}$PASSED przeszło${NC}, ${RED}$FAILED nie przeszło${NC}"
echo "=============================================="

if [ $FAILED -gt 0 ]; then
    exit 1
fi
exit 0
