using System;
using System.IO;
using System.Text;
using System.Runtime.InteropServices;
using Microsoft.Win32.SafeHandles;

namespace Unibag.Serial {
    public class Win32Serial : IDisposable {
        [DllImport("kernel32.dll", SetLastError = true, CharSet = CharSet.Auto)]
        private static extern SafeFileHandle CreateFile(
            string lpFileName,
            uint dwDesiredAccess,
            uint dwShareMode,
            IntPtr lpSecurityAttributes,
            uint dwCreationDisposition,
            uint dwFlagsAndAttributes,
            IntPtr hTemplateFile);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool GetCommState(SafeFileHandle hFile, ref DCB lpDCB);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool SetCommState(SafeFileHandle hFile, ref DCB lpDCB);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool SetCommTimeouts(SafeFileHandle hFile, ref COMMTIMEOUTS lpCommTimeouts);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool ReadFile(
            SafeFileHandle hFile,
            byte[] lpBuffer,
            uint nNumberOfBytesToRead,
            out uint lpNumberOfBytesRead,
            IntPtr lpOverlapped);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool WriteFile(
            SafeFileHandle hFile,
            byte[] lpBuffer,
            uint nNumberOfBytesToWrite,
            out uint lpNumberOfBytesWritten,
            IntPtr lpOverlapped);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool ClearCommError(
            SafeFileHandle hFile,
            out uint lpErrors,
            ref COMSTAT lpStat);

        [DllImport("kernel32.dll", SetLastError = true)]
        private static extern bool PurgeComm(SafeFileHandle hFile, uint dwFlags);

        [StructLayout(LayoutKind.Sequential)]
        public struct DCB {
            public int DCBlength;
            public uint BaudRate;
            public uint Flags;
            public ushort wReserved;
            public ushort XonLim;
            public ushort XoffLim;
            public byte ByteSize;
            public byte Parity;
            public byte StopBits;
            public sbyte XonChar;
            public sbyte XoffChar;
            public sbyte ErrorChar;
            public sbyte EofChar;
            public sbyte EvtChar;
            public ushort wReserved1;
        }

        [StructLayout(LayoutKind.Sequential)]
        public struct COMMTIMEOUTS {
            public uint ReadIntervalTimeout;
            public uint ReadTotalTimeoutMultiplier;
            public uint ReadTotalTimeoutConstant;
            public uint WriteTotalTimeoutMultiplier;
            public uint WriteTotalTimeoutConstant;
        }

        [StructLayout(LayoutKind.Sequential)]
        public struct COMSTAT {
            public uint Flags;
            public uint cbInQue;
            public uint cbOutQue;
        }

        private SafeFileHandle _handle;
        public bool IsOpen { get { return _handle != null && !_handle.IsInvalid && !_handle.IsClosed; } }
        public string PortName { get; private set; }

        public bool Open(string portName, int baudRate = 9600) {
            Close();
            PortName = portName;
            string fullPath = @"\\.\" + portName;
            _handle = CreateFile(fullPath, 0xC0000000, 0, IntPtr.Zero, 3, 0, IntPtr.Zero);
            if (_handle.IsInvalid) {
                int err = Marshal.GetLastWin32Error();
                throw new IOException("No se pudo abrir " + portName + ". Codigo: " + err);
            }

            // Configurar timeouts inmediatamente (muy importante para lectura no bloqueante)
            COMMTIMEOUTS timeouts = new COMMTIMEOUTS {
                ReadIntervalTimeout = 100,
                ReadTotalTimeoutMultiplier = 0,
                ReadTotalTimeoutConstant = 400,
                WriteTotalTimeoutMultiplier = 0,
                WriteTotalTimeoutConstant = 400
            };
            SetCommTimeouts(_handle, ref timeouts);

            // Intentar SetCommState pero tolerar si el driver CH340 devuelve 31
            try {
                DCB dcb = new DCB();
                dcb.DCBlength = Marshal.SizeOf(typeof(DCB));
                if (GetCommState(_handle, ref dcb)) {
                    dcb.BaudRate = (uint)baudRate;
                    SetCommState(_handle, ref dcb);
                }
            } catch {}

            // Limpiar buffers
            try { PurgeComm(_handle, 0x0004 | 0x0008); } catch {}

            return true;
        }

        public string ReadExisting() {
            if (!IsOpen) return "";
            COMSTAT stat = new COMSTAT();
            uint errors;
            ClearCommError(_handle, out errors, ref stat);
            if (stat.cbInQue == 0) return "";

            byte[] buf = new byte[stat.cbInQue];
            uint read;
            if (ReadFile(_handle, buf, (uint)buf.Length, out read, IntPtr.Zero) && read > 0) {
                return Encoding.ASCII.GetString(buf, 0, (int)read);
            }
            return "";
        }

        public string ReadBlock(int timeoutMs = 800) {
            if (!IsOpen) return "";
            COMMTIMEOUTS timeouts = new COMMTIMEOUTS {
                ReadIntervalTimeout = 50,
                ReadTotalTimeoutMultiplier = 0,
                ReadTotalTimeoutConstant = (uint)timeoutMs,
                WriteTotalTimeoutMultiplier = 0,
                WriteTotalTimeoutConstant = 200
            };
            SetCommTimeouts(_handle, ref timeouts);

            byte[] buf = new byte[512];
            uint read;
            if (ReadFile(_handle, buf, (uint)buf.Length, out read, IntPtr.Zero) && read > 0) {
                return Encoding.ASCII.GetString(buf, 0, (int)read);
            }
            return "";
        }

        public bool SendCommand(string cmd) {
            if (!IsOpen || string.IsNullOrEmpty(cmd)) return false;
            byte[] bytes = Encoding.ASCII.GetBytes(cmd);
            uint written;
            return WriteFile(_handle, bytes, (uint)bytes.Length, out written, IntPtr.Zero);
        }

        public void Close() {
            if (_handle != null && !_handle.IsClosed) {
                _handle.Close();
                _handle = null;
            }
        }

        public void Dispose() {
            Close();
        }
    }
}