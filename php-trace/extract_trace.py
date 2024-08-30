import os
import gzip
import re

trace_dir = 'tracefiles/'
output_file = 'output.log'

trace_lines = []

trace_files = [f for f in os.listdir(trace_dir) if f.endswith('.xt.gz')]

for trace_file in trace_files:
    trace_path = os.path.join(trace_dir, trace_file)
    
    with gzip.open(trace_path, 'rt') as f:
        for line in f:
            match = re.search(r'->.*\) ([^:]+):(\d+)', line)
            if match:
                file_path = f"{match.group(1)}:{match.group(2)}"
                trace_lines.append(file_path)

if trace_lines:
    with open(output_file, 'w') as out_f:
        out_f.write('\n'.join(trace_lines))
    print("\ntrace:\n"+'\n'.join(trace_lines))
else:
    print("No trace lines found.")

# Remove all files in src/tr/
for file_name in os.listdir(trace_dir):
    file_path = os.path.join(trace_dir, file_name)
    if os.path.isfile(file_path):
        os.remove(file_path)

