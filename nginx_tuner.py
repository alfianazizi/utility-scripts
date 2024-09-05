import os
import psutil
import math

def get_cpu_cores():
    return psutil.cpu_count()

def get_total_memory():
    return psutil.virtual_memory().total / (1024 * 1024)  # Convert to MB

def get_nginx_processes():
    return len([p for p in psutil.process_iter(['name']) if 'nginx' in p.info['name']])

def tune_nginx(cpu_cores, total_memory, nginx_processes):
    # Worker processes: Usually set to number of CPU cores
    worker_processes = cpu_cores

    # Worker connections: Rule of thumb is 1024 * number of CPU cores
    worker_connections = 1024 * cpu_cores

    # Max clients: worker_processes * worker_connections
    max_clients = worker_processes * worker_connections

    # Client body size: Adjust based on your needs, default to 1m
    client_body_size = "1m"

    # Keepalive timeout: Usually set between 30-65 seconds
    keepalive_timeout = 65

    # Server names hash bucket size: Adjust if you have many server names
    server_names_hash_bucket_size = 64

    # Types hash max size: Adjust if you have many MIME types
    types_hash_max_size = 2048

    # Open file cache: Adjust based on total memory
    open_file_cache_max = min(math.floor(total_memory / 10), 65536)
    open_file_cache_inactive = math.floor(open_file_cache_max / 2)

    return {
        "worker_processes": worker_processes,
        "worker_connections": worker_connections,
        "max_clients": max_clients,
        "client_body_size": client_body_size,
        "keepalive_timeout": keepalive_timeout,
        "server_names_hash_bucket_size": server_names_hash_bucket_size,
        "types_hash_max_size": types_hash_max_size,
        "open_file_cache_max": open_file_cache_max,
        "open_file_cache_inactive": open_file_cache_inactive
    }

def main():
    cpu_cores = get_cpu_cores()
    total_memory = get_total_memory()
    nginx_processes = get_nginx_processes()

    print(f"CPU Cores: {cpu_cores}")
    print(f"Total Memory: {total_memory:.2f} MB")
    print(f"Current Nginx Processes: {nginx_processes}")
    print("\nRecommended Nginx Configuration:")

    config = tune_nginx(cpu_cores, total_memory, nginx_processes)

    for key, value in config.items():
        print(f"{key}: {value}")

    print("\nSample Nginx Configuration Snippet:")
    print(f"""
worker_processes {config['worker_processes']};

events {{
    worker_connections {config['worker_connections']};
}}

http {{
    client_max_body_size {config['client_body_size']};
    keepalive_timeout {config['keepalive_timeout']};
    server_names_hash_bucket_size {config['server_names_hash_bucket_size']};
    types_hash_max_size {config['types_hash_max_size']};

    open_file_cache max={config['open_file_cache_max']} inactive={config['open_file_cache_inactive']}s;
    open_file_cache_valid 60s;
    open_file_cache_min_uses 2;
    open_file_cache_errors on;

    # Other settings...
}}
    """)

if __name__ == "__main__":
    main()