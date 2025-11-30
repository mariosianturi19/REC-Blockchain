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
