#!/bin/bash

# REC Blockchain Step 1-2 Latency Testing Script
# Testing untuk flow: Pending → Verified (tanpa CouchDB)

echo "🔄 REC BLOCKCHAIN STEP 1-2 LATENCY TESTING"
echo "============================================"
echo "Testing scenario flow pending → verified:"
echo "📋 Step 1: GENERATOR → Lapor produksi energi (status: PENDING)"
echo "📋 Step 2: ISSUER → Verifikasi laporan energi (status: VERIFIED)"
echo "📋 Step 3: BUYER → Query data energi yang sudah diverifikasi"
echo ""

# Set paths
JMETER_HOME="/home/najla/Downloads/apache-jmeter-5.6.3"
TEST_PLAN="/home/najla/Downloads/REC-Blockchain/jmeter-step12-test.jmx"
RESULTS_DIR="/home/najla/Downloads/REC-Blockchain/step12-results"
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
echo "🧪 Starting Step 1-2 Latency Tests..."
echo ""

# Test scenarios explanation
echo "📊 TEST SCENARIOS FOR PENDING → VERIFIED FLOW:"
echo ""
echo "🏭 STEP 1 - GENERATOR Testing:"
echo "   - Endpoint: POST /api/energy/submit"
echo "   - Scenario: Generator melaporkan produksi energi"
echo "   - Result: Data tersimpan dengan status PENDING"
echo "   - Load: 5 threads, 10 loops (50 laporan energi)"
echo ""
echo "🏛️ STEP 2 - ISSUER Testing:"
echo "   - Endpoint: PUT /api/energy/verify/:energyDataId"
echo "   - Scenario: Issuer memverifikasi laporan energi"
echo "   - Result: Status berubah dari PENDING → VERIFIED"
echo "   - Load: 3 threads, 10 loops (30 verifikasi)"
echo ""
echo "🏢 STEP 3 - BUYER Testing:"
echo "   - Endpoint: GET /api/energy"
echo "   - Scenario: Buyer query data energi yang sudah diverifikasi"
echo "   - Result: Mendapat list data energi dengan status VERIFIED"
echo "   - Load: 8 threads, 15 loops (120 query)"
echo ""

# Run JMeter test
$JMETER_HOME/bin/jmeter -n \
    -t $TEST_PLAN \
    -l $RESULTS_DIR/step12_results_$TIMESTAMP.jtl \
    -e \
    -o $RESULTS_DIR/step12_html_report_$TIMESTAMP \
    -j $RESULTS_DIR/step12_jmeter_$TIMESTAMP.log

echo ""
echo "✅ Step 1-2 JMeter test completed!"
echo ""

# Create enhanced analysis script for Step 1-2
cat > $RESULTS_DIR/analyze_step12_latency.py << 'EOF'
import pandas as pd
import sys
import numpy as np
from datetime import datetime

def analyze_step12_latency(file_path):
    try:
        # Read JTL file
        df = pd.read_csv(file_path)
        
        print("🔄 STEP 1-2 LATENCY ANALYSIS (PENDING → VERIFIED)")
        print("=" * 60)
        print(f"🕐 Test completed at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"📁 Total samples: {len(df)}")
        print()
        
        # Step mapping based on test names
        step_mapping = {
            'Generator Energy Submit': '📋 STEP 1: GENERATOR (Submit)',
            'Issuer Energy Verify': '📋 STEP 2: ISSUER (Verify)',
            'Buyer Energy Query': '📋 STEP 3: BUYER (Query)',
            'Energy Submit': '📋 STEP 1: GENERATOR (Submit)',
            'Energy Verify': '📋 STEP 2: ISSUER (Verify)',
            'Energy Query': '📋 STEP 3: BUYER (Query)'
        }
        
        # Group by label (test name) and map to steps
        print("📊 LATENCY ANALYSIS PER STEP")
        print("=" * 50)
        
        step_results = {}
        for label in df['label'].unique():
            test_data = df[df['label'] == label]
            step_name = step_mapping.get(label, f'🔧 {label}')
            
            avg_latency = test_data['elapsed'].mean()
            p95_latency = test_data['elapsed'].quantile(0.95)
            error_rate = (test_data['success'] == False).sum() / len(test_data) * 100
            
            step_results[step_name] = {
                'avg_latency': avg_latency,
                'p95_latency': p95_latency,
                'error_rate': error_rate,
                'samples': len(test_data)
            }
            
            print(f"{step_name}")
            print(f"   Samples: {len(test_data)}")
            print(f"   Average Latency: {avg_latency:.2f} ms")
            print(f"   Median Latency: {test_data['elapsed'].median():.2f} ms")
            print(f"   95th Percentile: {p95_latency:.2f} ms")
            print(f"   99th Percentile: {test_data['elapsed'].quantile(0.99):.2f} ms")
            print(f"   Min Latency: {test_data['elapsed'].min():.2f} ms")
            print(f"   Max Latency: {test_data['elapsed'].max():.2f} ms")
            print(f"   Error Rate: {error_rate:.2f}%")
            
            # Calculate throughput more safely
            if len(test_data) > 1:
                time_diff = test_data['timeStamp'].max() - test_data['timeStamp'].min()
                if time_diff > 0:
                    throughput = len(test_data) / time_diff * 1000
                else:
                    throughput = 0
            else:
                throughput = 0
                
            print(f"   Throughput: {throughput:.2f} req/sec")
            print()
        
        # Overall statistics
        print("🔹 OVERALL STEP 1-2 STATISTICS")
        print("=" * 40)
        print(f"   Total Average Latency: {df['elapsed'].mean():.2f} ms")
        print(f"   Total 95th Percentile: {df['elapsed'].quantile(0.95):.2f} ms")
        print(f"   Total Error Rate: {(df['success'] == False).sum() / len(df) * 100:.2f}%")
        
        # Calculate overall throughput safely
        if len(df) > 1:
            overall_time_diff = df['timeStamp'].max() - df['timeStamp'].min()
            if overall_time_diff > 0:
                overall_throughput = len(df) / overall_time_diff * 1000
            else:
                overall_throughput = 0
        else:
            overall_throughput = 0
            
        print(f"   Overall Throughput: {overall_throughput:.2f} req/sec")
        print()
        
        # Research paper metrics
        avg_latency = df['elapsed'].mean()
        p95_latency = df['elapsed'].quantile(0.95)
        overall_error_rate = (df['success'] == False).sum() / len(df) * 100
        
        print("📝 FOR RESEARCH PAPER - STEP 1-2 BLOCKCHAIN LATENCY:")
        print("=" * 55)
        print(f"   Flow: Generator Submit → Issuer Verify → Buyer Query")
        print(f"   Average Transaction Latency: {avg_latency:.2f} ms")
        print(f"   95th Percentile Latency: {p95_latency:.2f} ms")
        print(f"   System Error Rate: {overall_error_rate:.2f}%")
        
        if avg_latency < 100:
            category = "Excellent (< 100ms)"
            conclusion = "Sistem blockchain REC Step 1-2 sangat responsif"
        elif avg_latency < 500:
            category = "Good (100-500ms)"
            conclusion = "Sistem blockchain REC Step 1-2 responsif untuk produksi"
        elif avg_latency < 1000:
            category = "Acceptable (500-1000ms)"
            conclusion = "Sistem blockchain REC Step 1-2 dapat diterima"
        else:
            category = "Needs Improvement (> 1000ms)"
            conclusion = "Sistem blockchain REC Step 1-2 perlu optimisasi"
            
        print(f"   Latency Category: {category}")
        print(f"   Conclusion: {conclusion}")
        print()
        
        # Step performance ranking
        print("🏆 STEP PERFORMANCE RANKING:")
        print("=" * 35)
        
        sorted_steps = sorted(step_results.items(), key=lambda x: x[1]['avg_latency'])
        
        for i, (step, data) in enumerate(sorted_steps, 1):
            status = "✅ Success" if data['error_rate'] < 5 else "⚠️ Issues" if data['error_rate'] < 50 else "❌ Failed"
            print(f"   {i}. {step}")
            print(f"      Latency: {data['avg_latency']:.2f} ms | Error: {data['error_rate']:.1f}% | {status}")
        
        print()
        
        # Flow analysis
        print("🔄 FLOW ANALYSIS:")
        print("=" * 20)
        if len(sorted_steps) >= 3:
            step1_error = sorted_steps[0][1]['error_rate'] if 'STEP 1' in sorted_steps[0][0] else None
            step2_error = sorted_steps[1][1]['error_rate'] if 'STEP 2' in sorted_steps[1][0] else None
            
            if step1_error is not None and step2_error is not None:
                if step1_error < 5 and step2_error < 5:
                    print("   ✅ Flow PENDING → VERIFIED: Berjalan lancar")
                elif step1_error < 50 and step2_error < 50:
                    print("   ⚠️ Flow PENDING → VERIFIED: Ada beberapa masalah")
                else:
                    print("   ❌ Flow PENDING → VERIFIED: Perlu perbaikan")
        
        print()
        
    except Exception as e:
        print(f"Error analyzing results: {e}")

if __name__ == "__main__":
    if len(sys.argv) > 1:
        analyze_step12_latency(sys.argv[1])
    else:
        print("Usage: python analyze_step12_latency.py <jtl_file>")
EOF

# Run analysis if Python is available
if command -v python3 > /dev/null 2>&1; then
    python3 $RESULTS_DIR/analyze_step12_latency.py $RESULTS_DIR/step12_results_$TIMESTAMP.jtl
else
    echo "Python3 not found. Manual analysis required."
    echo "Raw results saved to: $RESULTS_DIR/step12_results_$TIMESTAMP.jtl"
fi

echo ""
echo "📁 Files generated:"
echo "   • Raw results: $RESULTS_DIR/step12_results_$TIMESTAMP.jtl"
echo "   • HTML report: $RESULTS_DIR/step12_html_report_$TIMESTAMP/index.html"
echo "   • JMeter log: $RESULTS_DIR/step12_jmeter_$TIMESTAMP.log"
echo "   • Analysis script: $RESULTS_DIR/analyze_step12_latency.py"
echo ""
echo "🎯 RESEARCH INSIGHTS - STEP 1-2:"
echo "1. 🏭 Generator Submit Latency → Waktu lapor produksi energi"
echo "2. 🏛️ Issuer Verify Latency → Waktu verifikasi laporan"  
echo "3. 🏢 Buyer Query Latency → Waktu query data terverifikasi"
echo "4. 📊 End-to-end latency → Performa flow pending → verified"
echo ""
echo "✨ Step 1-2 latency testing completed successfully!"