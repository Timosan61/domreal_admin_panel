#!/bin/bash
# Тестовые команды для API communication_metrics.php

BASE_URL="http://localhost:8080/api/communication_metrics.php"

echo "========================================"
echo "Communication Metrics API Test"
echo "========================================"
echo ""

# Test 1: Summary (general metrics)
echo "1. Summary metrics (last 7 days):"
curl -s "${BASE_URL}?type=summary&period=7d" | python3 -m json.tool | head -50
echo ""
echo "----------------------------------------"
echo ""

# Test 2: Interruptions (detailed)
echo "2. Interruption metrics (last 30 days):"
curl -s "${BASE_URL}?type=interruptions&period=30d" | python3 -m json.tool | head -50
echo ""
echo "----------------------------------------"
echo ""

# Test 3: Talk-to-Listen ratio
echo "3. Talk-to-Listen ratio (last 14 days):"
curl -s "${BASE_URL}?type=talk_listen&period=14d" | python3 -m json.tool | head -50
echo ""
echo "----------------------------------------"
echo ""

# Test 4: Filter by department
echo "4. Summary for specific department:"
curl -s "${BASE_URL}?type=summary&period=30d&department=3%20отдел%20Морозова%20Наталья" | python3 -m json.tool | head -50
echo ""
echo "----------------------------------------"
echo ""

# Test 5: Filter by manager
echo "5. Metrics for specific manager:"
curl -s "${BASE_URL}?type=summary&period=30d&manager=Дмитрий%20Лазаревич%20Бердзенов" | python3 -m json.tool
echo ""
echo "----------------------------------------"
echo ""

echo "Tests completed!"
