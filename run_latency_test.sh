#!/bin/bash

# REC Blockchain Latency Testing Script
# Author: Performance Testing Team
# Date: $(date)

echo "🚀 REC BLOCKCHAIN LATENCY TESTING WITH JMETER"
echo "=============================================="

# Set paths
JMETER_HOME="/home/najla/Downloads/apache-jmeter-5.6.3"
TEST_PLAN="/home/najla/Downloads/REC-Blockchain/jmeter-latency-test.jmx"
RESULTS_DIR="/home/najla/Downloads/REC-Blockchain/latency-results"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")

# Create results directory
mkdir -p $RESULTS_DIR

echo "📁 Results will be saved to: $RESULTS_DIR"
echo ""

# Check if API server is running
echo "🔍 Checking if REC API server is running..."
if curl -s http://localhost:3000/health > /dev/null 2>&1; then
    echo "✅ API server is running on port 3000"
else
    echo "❌ API server is not running on port 3000"
    echo "Please start your API server first:"
    echo "   cd /home/najla/Downloads/REC-Blockchain/rec-api-server"
    echo "   npm start"
    exit 1
fi

echo ""
echo "🧪 Starting JMeter Latency Tests..."
echo ""

# Run JMeter test in non-GUI mode for better performance
echo "📊 Running test scenarios:"
echo "   - REC Certificate Creation: 10 threads, 50 loops"
echo "   - Certificate Query: 20 threads, 100 loops" 
echo "   - Purchase Transactions: 5 threads, 30 loops"
echo ""

$JMETER_HOME/bin/jmeter -n \
    -t $TEST_PLAN \
    -l $RESULTS_DIR/latency_results_$TIMESTAMP.jtl \
    -e \
    -o $RESULTS_DIR/html_report_$TIMESTAMP \
    -j $RESULTS_DIR/jmeter_$TIMESTAMP.log

echo ""
echo "✅ JMeter test completed!"
echo ""

# Generate summary report
echo "📈 LATENCY TEST SUMMARY"
echo "======================"

# Parse results for latency metrics
if [ -f "$RESULTS_DIR/latency_results_$TIMESTAMP.jtl" ]; then
    echo ""
    echo "📋 Processing results..."
    
    # Create summary analysis script
    cat > $RESULTS_DIR/analyze_latency.py << 'EOF'
import pandas as pd
import sys
import numpy as np
from datetime import datetime

def analyze_latency(file_path):
    try:
        # Read JTL file
        df = pd.read_csv(file_path)
        
        print("📊 LATENCY ANALYSIS RESULTS")
        print("=" * 50)
        print(f"🕐 Test completed at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"📁 Total samples: {len(df)}")
        print()
        
        # Group by label (test name)
        for label in df['label'].unique():
            test_data = df[df['label'] == label]
            
            print(f"🔸 {label}")
            print(f"   Samples: {len(test_data)}")
            print(f"   Average Response Time: {test_data['elapsed'].mean():.2f} ms")
            print(f"   Median Response Time: {test_data['elapsed'].median():.2f} ms")
            print(f"   95th Percentile: {test_data['elapsed'].quantile(0.95):.2f} ms")
            print(f"   99th Percentile: {test_data['elapsed'].quantile(0.99):.2f} ms")
            print(f"   Min Response Time: {test_data['elapsed'].min():.2f} ms")
            print(f"   Max Response Time: {test_data['elapsed'].max():.2f} ms")
            print(f"   Error Rate: {(test_data['success'] == False).sum() / len(test_data) * 100:.2f}%")
            print(f"   Throughput: {len(test_data) / (test_data['timeStamp'].max() - test_data['timeStamp'].min()) * 1000:.2f} req/sec")
            print()
        
        # Overall statistics
        print("🔹 OVERALL STATISTICS")
        print(f"   Total Average Response Time: {df['elapsed'].mean():.2f} ms")
        print(f"   Total 95th Percentile: {df['elapsed'].quantile(0.95):.2f} ms")
        print(f"   Total Error Rate: {(df['success'] == False).sum() / len(df) * 100:.2f}%")
        print(f"   Overall Throughput: {len(df) / (df['timeStamp'].max() - df['timeStamp'].min()) * 1000:.2f} req/sec")
        print()
        
        # Latency categories for research paper
        avg_latency = df['elapsed'].mean()
        p95_latency = df['elapsed'].quantile(0.95)
        
        print("📝 FOR RESEARCH PAPER:")
        print(f"   Average Latency: {avg_latency:.2f} ms")
        print(f"   95th Percentile Latency: {p95_latency:.2f} ms")
        
        if avg_latency < 100:
            category = "Excellent (< 100ms)"
        elif avg_latency < 500:
            category = "Good (100-500ms)"
        elif avg_latency < 1000:
            category = "Acceptable (500-1000ms)"
        else:
            category = "Needs Improvement (> 1000ms)"
            
        print(f"   Latency Category: {category}")
        print()
        
    except Exception as e:
        print(f"Error analyzing results: {e}")

if __name__ == "__main__":
    if len(sys.argv) > 1:
        analyze_latency(sys.argv[1])
    else:
        print("Usage: python analyze_latency.py <jtl_file>")
EOF
    
    # Run analysis if Python is available
    if command -v python3 > /dev/null 2>&1; then
        python3 $RESULTS_DIR/analyze_latency.py $RESULTS_DIR/latency_results_$TIMESTAMP.jtl
    else
        echo "Python3 not found. Manual analysis required."
        echo "Raw results saved to: $RESULTS_DIR/latency_results_$TIMESTAMP.jtl"
    fi
    
    echo ""
    echo "📁 Files generated:"
    echo "   • Raw results: $RESULTS_DIR/latency_results_$TIMESTAMP.jtl"
    echo "   • HTML report: $RESULTS_DIR/html_report_$TIMESTAMP/index.html"
    echo "   • JMeter log: $RESULTS_DIR/jmeter_$TIMESTAMP.log"
    echo "   • Analysis script: $RESULTS_DIR/analyze_latency.py"
    
else
    echo "❌ Results file not found!"
fi

echo ""
echo "🎯 NEXT STEPS FOR RESEARCH:"
echo "1. Open HTML report for detailed graphs"
echo "2. Use latency metrics in your research paper"
echo "3. Compare with other blockchain platforms"
echo "4. Run tests with different load patterns"
echo ""
echo "✨ Latency testing completed successfully!"