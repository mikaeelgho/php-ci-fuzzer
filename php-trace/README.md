# Install
```
cp your_code_repo/ src/
mkdir tracefiles
docker build -t php-trace .
docker run -it --rm -v $(pwd)/../:/app php-trace php /app/example/src/main.php hello && python extract_trace.py
```