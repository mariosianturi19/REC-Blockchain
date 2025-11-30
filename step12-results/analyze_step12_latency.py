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
