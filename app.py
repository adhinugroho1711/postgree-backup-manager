import os
import streamlit as st
import psycopg2
from datetime import datetime, timedelta
import subprocess
import shutil
from pathlib import Path
from dotenv import load_dotenv
import boto3
from botocore.exceptions import NoCredentialsError
import time

# Load environment variables
load_dotenv()

# Konfigurasi
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'port': os.getenv('DB_PORT', '5432'),
    'database': os.getenv('DB_NAME', 'postgres'),
    'user': os.getenv('DB_USER', 'postgres'),
    'password': os.getenv('DB_PASSWORD', '')
}

BACKUP_DIR = os.getenv('BACKUP_DIR', './backups')
RETENTION_DAYS = int(os.getenv('RETENTION_DAYS', '7'))

# Buat direktori backup jika belum ada
os.makedirs(BACKUP_DIR, exist_ok=True)

def test_connection():
    """Test koneksi ke database"""
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        conn.close()
        return True, "Koneksi berhasil!"
    except Exception as e:
        return False, f"Gagal terhubung ke database: {str(e)}"

def create_backup():
    """Membuat backup database"""
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    backup_file = os.path.join(BACKUP_DIR, f"backup_{timestamp}.sql")
    
    try:
        # Gunakan pg_dump untuk membuat backup
        cmd = [
            'pg_dump',
            '-h', DB_CONFIG['host'],
            '-p', str(DB_CONFIG['port']),
            '-U', DB_CONFIG['user'],
            '-d', DB_CONFIG['database'],
            '-f', backup_file
        ]
        
        # Set environment variable untuk password
        env = os.environ.copy()
        env['PGPASSWORD'] = DB_CONFIG['password']
        
        # Eksekusi perintah
        result = subprocess.run(
            cmd, 
            env=env,
            capture_output=True,
            text=True
        )
        
        if result.returncode == 0:
            return True, f"Backup berhasil dibuat: {backup_file}", backup_file
        else:
            return False, f"Gagal membuat backup: {result.stderr}", None
            
    except Exception as e:
        return False, f"Terjadi kesalahan: {str(e)}", None

def list_backups():
    """Mendapatkan daftar backup yang tersedia"""
    backups = []
    for file in sorted(os.listdir(BACKUP_DIR), reverse=True):
        if file.startswith('backup_') and file.endswith('.sql'):
            file_path = os.path.join(BACKUP_DIR, file)
            file_time = os.path.getmtime(file_path)
            file_date = datetime.fromtimestamp(file_time).strftime('%Y-%m-%d %H:%M:%S')
            file_size = os.path.getsize(file_path) / (1024 * 1024)  # dalam MB
            backups.append({
                'name': file,
                'path': file_path,
                'date': file_date,
                'size': f"{file_size:.2f} MB"
            })
    return backups

def restore_backup(backup_path):
    """Merestore database dari backup"""
    try:
        # Drop database yang ada dan buat yang baru
        conn = psycopg2.connect(
            host=DB_CONFIG['host'],
            port=DB_CONFIG['port'],
            user=DB_CONFIG['user'],
            password=DB_CONFIG['password'],
            database='postgres'  # Connect ke database default
        )
        conn.autocommit = True
        cursor = conn.cursor()
        
        # Hentikan koneksi ke database yang akan di-drop
        cursor.execute(f"""
            SELECT pg_terminate_backend(pg_stat_activity.pid)
            FROM pg_stat_activity
            WHERE pg_stat_activity.datname = %s
            AND pid <> pg_backend_pid();
        """, (DB_CONFIG['database'],))
        
        # Drop dan buat ulang database
        cursor.execute(f"DROP DATABASE IF EXISTS {DB_CONFIG['database']};")
        cursor.execute(f"CREATE DATABASE {DB_CONFIG['database']};")
        cursor.close()
        conn.close()
        
        # Restore database
        cmd = [
            'psql',
            '-h', DB_CONFIG['host'],
            '-p', str(DB_CONFIG['port']),
            '-U', DB_CONFIG['user'],
            '-d', DB_CONFIG['database'],
            '-f', backup_path
        ]
        
        env = os.environ.copy()
        env['PGPASSWORD'] = DB_CONFIG['password']
        
        result = subprocess.run(
            cmd, 
            env=env,
            capture_output=True,
            text=True
        )
        
        if result.returncode == 0:
            return True, "Restore berhasil!"
        else:
            return False, f"Gagal restore: {result.stderr}"
            
    except Exception as e:
        return False, f"Terjadi kesalahan: {str(e)}"

def cleanup_old_backups():
    """Menghapus backup yang lebih lama dari RETENTION_DAYS"""
    now = datetime.now()
    deleted = 0
    
    for file in os.listdir(BACKUP_DIR):
        if file.startswith('backup_') and file.endswith('.sql'):
            file_path = os.path.join(BACKUP_DIR, file)
            file_time = datetime.fromtimestamp(os.path.getmtime(file_path))
            
            if (now - file_time).days > RETENTION_DAYS:
                try:
                    os.remove(file_path)
                    deleted += 1
                except Exception as e:
                    st.error(f"Gagal menghapus {file}: {str(e)}")
    
    return deleted

def upload_to_s3(file_path):
    """Mengupload file ke Amazon S3"""
    try:
        s3 = boto3.client(
            's3',
            aws_access_key_id=os.getenv('AWS_ACCESS_KEY_ID'),
            aws_secret_access_key=os.getenv('AWS_SECRET_ACCESS_KEY')
        )
        
        bucket_name = os.getenv('S3_BUCKET')
        s3_prefix = os.getenv('S3_PREFIX', 'postgres_backups/')
        
        # Pastikan prefix diakhiri dengan /
        if not s3_prefix.endswith('/'):
            s3_prefix += '/'
            
        # Nama file di S3
        s3_key = f"{s3_prefix}{os.path.basename(file_path)}"
        
        # Upload file
        s3.upload_file(file_path, bucket_name, s3_key)
        return True, f"Berhasil mengupload ke S3: {s3_key}"
        
    except NoCredentialsError:
        return False, "Kredensial AWS tidak ditemukan. Pastikan variabel lingkungan AWS_ACCESS_KEY_ID dan AWS_SECRET_ACCESS_KEY sudah diatur."
    except Exception as e:
        return False, f"Gagal mengupload ke S3: {str(e)}"

def main():
    st.title("PostgreSQL Backup Manager")
    
    # Sidebar untuk navigasi
    menu = st.sidebar.selectbox(
        "Menu",
        ["Dashboard", "Backup", "Restore", "Pengaturan"]
    )
    
    if menu == "Dashboard":
        st.header("Dashboard")
        
        # Status koneksi database
        st.subheader("Status Koneksi Database")
        if st.button("Test Koneksi"):
            success, message = test_connection()
            if success:
                st.success(message)
            else:
                st.error(message)
        
        # Statistik backup
        st.subheader("Statistik Backup")
        backups = list_backups()
        col1, col2 = st.columns(2)
        col1.metric("Total Backup", len(backups))
        
        # Hitung ukuran total backup
        total_size = sum(float(b['size'].split()[0]) for b in backups)
        col2.metric("Total Penyimpanan Digunakan", f"{total_size:.2f} MB")
        
        # Daftar backup terbaru
        st.subheader("Backup Terbaru")
        if backups:
            st.table(backups[:5])  # Tampilkan 5 backup terbaru
        else:
            st.info("Belum ada backup yang tersedia.")
    
    elif menu == "Backup":
        st.header("Buat Backup")
        
        if st.button("Buat Backup Sekarang"):
            with st.spinner("Membuat backup..."):
                success, message, backup_file = create_backup()
                if success:
                    st.success(message)
                    
                    # Upload ke S3 jika konfigurasi tersedia
                    if all(os.getenv(k) for k in ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'S3_BUCKET']):
                        st.info("Mengupload backup ke S3...")
                        upload_success, upload_message = upload_to_s3(backup_file)
                        if upload_success:
                            st.success(upload_message)
                        else:
                            st.warning(upload_message)
                else:
                    st.error(message)
        
        # Jadwal backup otomatis
        st.subheader("Jadwal Backup Otomatis")
        st.info("Fitur jadwal backup otomatis memerlukan konfigurasi cron job atau task scheduler di sistem operasi.")
    
    elif menu == "Restore":
        st.header("Restore Database")
        
        backups = list_backups()
        if not backups:
            st.warning("Tidak ada backup yang tersedia untuk direstore.")
        else:
            backup_options = [f"{b['name']} ({b['date']}, {b['size']})" for b in backups]
            selected_backup = st.selectbox("Pilih Backup", backup_options)
            
            if st.button("Restore Database", type="primary"):
                if st.warning("Peringatan: Ini akan menghapus semua data yang ada di database saat ini. Lanjutkan?"):
                    selected_index = backup_options.index(selected_backup)
                    backup_path = backups[selected_index]['path']
                    
                    with st.spinner("Memulihkan database..."):
                        success, message = restore_backup(backup_path)
                        if success:
                            st.success(message)
                        else:
                            st.error(message)
    
    elif menu == "Pengaturan":
        st.header("Pengaturan")
        
        st.subheader("Konfigurasi Database")
        st.json({
            "Host": DB_CONFIG['host'],
            "Port": DB_CONFIG['port'],
            "Database": DB_CONFIG['database'],
            "User": DB_CONFIG['user']
        })
        
        st.subheader("Konfigurasi Backup")
        st.write(f"Direktori Backup: `{os.path.abspath(BACKUP_DIR)}`")
        st.write(f"Retensi Backup: {RETENTION_DAYS} hari")
        
        # Tampilkan penggunaan ruang disk
        if os.path.exists(BACKUP_DIR):
            total_size = sum(f.stat().st_size for f in Path(BACKUP_DIR).glob('*') if f.is_file()) / (1024 * 1024)
            st.write(f"Total Penggunaan Ruang: {total_size:.2f} MB")
        
        # Tombol untuk membersihkan backup lama
        if st.button("Bersihkan Backup Lama"):
            deleted = cleanup_old_backups()
            if deleted > 0:
                st.success(f"Berhasil menghapus {deleted} backup lama.")
            else:
                st.info("Tidak ada backup lama yang perlu dihapus.")
            
            # Refresh halaman untuk memperbarui tampilan
            st.experimental_rerun()
        
        # Toggle untuk menampilkan konfigurasi S3
        if st.checkbox("Tampilkan Konfigurasi S3"):
            st.subheader("Konfigurasi Amazon S3")
            if all(os.getenv(k) for k in ['AWS_ACCESS_KEY_ID', 'AWS_SECRET_ACCESS_KEY', 'S3_BUCKET']):
                st.success("Konfigurasi S3 terdeteksi.")
                st.write(f"S3 Bucket: {os.getenv('S3_BUCKET')}")
                st.write(f"S3 Prefix: {os.getenv('S3_PREFIX', 'postgres_backups/')}")
            else:
                st.warning("Konfigurasi S3 tidak lengkap. Tambahkan di file .env untuk mengaktifkan fitur backup ke S3.")

if __name__ == "__main__":
    main()
