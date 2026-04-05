import paramiko
import os

host = '192.168.1.169'
user = 'root'
password = 'Kookies'
local_file = '/Users/franck/xcode/logs.php'
remote_file = '/home/franck/Raid2/www/logs.php'

try:
    ssh = paramiko.SSHClient()
    ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    ssh.connect(host, port=22, username=user, password=password, timeout=10)
    
    sftp = ssh.open_sftp()
    sftp.put(local_file, remote_file)
    sftp.close()
    ssh.close()
    
    print(f"OK - {local_file} uploade vers {host}:{remote_file}")
except Exception as e:
    print(f"ERREUR: {e}")
