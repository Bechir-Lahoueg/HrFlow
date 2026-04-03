#!/bin/bash

# AI Service CLI Test Runner
# Script to easily run the AIService CLI tester

set -e

PROJECT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$PROJECT_DIR"

# Check if API_KEY is set
if [ -z "$API_KEY" ]; then
    echo "❌ API_KEY environment variable not set"
    echo ""
    echo "Please set it first:"
    echo "  export API_KEY='your-groq-api-key'"
    echo ""
    exit 1
fi

# Check if .env file exists and load it
if [ -f ".env" ]; then
    set -a
    source .env
    set +a
fi

echo "🔨 Building project..."
if mvn clean compile -q 2>/dev/null; then
    echo "✅ Build successful"
else
    echo "❌ Build failed"
    exit 1
fi

echo ""
echo "🚀 Starting AI Service CLI Tester..."
echo ""

# Get the MySQL connector JAR version
MYSQL_JAR=$(ls ~/.m2/repository/mysql/mysql-connector-java/*/mysql-connector-java-*.jar 2>/dev/null | head -1)
JSON_JAR=$(ls ~/.m2/repository/org/json/json/*/json-*.jar 2>/dev/null | head -1)

if [ -z "$MYSQL_JAR" ] || [ -z "$JSON_JAR" ]; then
    echo "⚠️  Missing dependencies, downloading with Maven..."
    mvn dependency:resolve -q
    MYSQL_JAR=$(ls ~/.m2/repository/mysql/mysql-connector-java/*/mysql-connector-java-*.jar | head -1)
    JSON_JAR=$(ls ~/.m2/repository/org/json/json/*/json-*.jar | head -1)
fi

# Run the CLI
export API_KEY
java -cp "target/classes:$MYSQL_JAR:$JSON_JAR" service.DataAnalysisAgentCLI