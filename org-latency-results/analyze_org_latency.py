import pandas as pd
import sys
import numpy as np
from datetime import datetime

def analyze_org_latency(file_path):
    try:
        # Read JTL file
        df = pd.read_csv(file_path)
        
        print("🏢 MULTI-ORGANIZATION LATENCY ANALYSIS")
        print("=" * 60)
        print(f"🕐 Test completed at: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
        print(f"📁 Total samples: {len(df)}")
        print()
        
        # Organization mapping based on test names
        org_mapping = {
            'Generator Energy Report': '🏭 GENERATOR',
            'Issuer Certificate Verification': '🏛️ ISSUER', 
            'Buyer Certificate Query': '🏢 BUYER',
            'Create REC Certificate': '🏛️ ISSUER',
            'Query All Certificates': '🏢 BUYER'
        }
        
        # Group by label (test name) and map to organizations
        print("📊 LATENCY ANALYSIS PER ORGANIZATION")
        print("=" * 60)
        
        for label in df['label'].unique():
            test_data = df[df['label'] == label]
            org_name = org_mapping.get(label, '🔧 SYSTEM')
            
            print(f"{org_name}")
            print(f"   Test: {label}")
            print(f"   Samples: {len(test_data)}")
            print(f"   Average Response Time: {test_data['elapsed'].mean():.2f} ms")
            print(f"   Median Response Time: {test_data['elapsed'].median():.2f} ms")
            print(f"   95th Percentile: {test_data['elapsed'].quantile(0.95):.2f} ms")
            print(f"   99th Percentile: {test_data['elapsed'].quantile(0.99):.2f} ms")
            print(f"   Min Response Time: {test_data['elapsed'].min():.2f} ms")
            print(f"   Max Response Time: {test_data['elapsed'].max():.2f} ms")
            print(f"   Error Rate: {(test_data['success'] == False).sum() / len(test_data) * 100:.2f}%")
            
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
        print("🔹 OVERALL SYSTEM STATISTICS")
        print("=" * 40)
        print(f"   Total Average Response Time: {df['elapsed'].mean():.2f} ms")
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
        
        print("📝 FOR RESEARCH PAPER - BLOCKCHAIN REC LATENCY:")
        print("=" * 50)
        print(f"   Average Transaction Latency: {avg_latency:.2f} ms")
        print(f"   95th Percentile Latency: {p95_latency:.2f} ms")
        
        if avg_latency < 100:
            category = "Excellent (< 100ms)"
            conclusion = "Sistem blockchain REC sangat responsif"
        elif avg_latency < 500:
            category = "Good (100-500ms)"
            conclusion = "Sistem blockchain REC responsif untuk produksi"
        elif avg_latency < 1000:
            category = "Acceptable (500-1000ms)"
            conclusion = "Sistem blockchain REC dapat diterima untuk produksi"
        else:
            category = "Needs Improvement (> 1000ms)"
            conclusion = "Sistem blockchain REC perlu optimisasi"
            
        print(f"   Latency Category: {category}")
        print(f"   Conclusion: {conclusion}")
        print()
        
        # Organization performance ranking
        print("🏆 ORGANIZATION PERFORMANCE RANKING:")
        print("=" * 40)
        org_performance = []
        
        for label in df['label'].unique():
            test_data = df[df['label'] == label]
            org_name = org_mapping.get(label, 'SYSTEM')
            avg_response = test_data['elapsed'].mean()
            org_performance.append((org_name, label, avg_response))
        
        # Sort by average response time (ascending = better)
        org_performance.sort(key=lambda x: x[2])
        
        for i, (org, test, avg_time) in enumerate(org_performance, 1):
            print(f"   {i}. {org}: {avg_time:.2f} ms ({test})")
        
        print()
        
    except Exception as e:
        print(f"Error analyzing results: {e}")

if __name__ == "__main__":
    if len(sys.argv) > 1:
        analyze_org_latency(sys.argv[1])
    else:
        print("Usage: python analyze_org_latency.py <jtl_file>")
