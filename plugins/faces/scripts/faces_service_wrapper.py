import win32serviceutil
import win32service
import win32event
import subprocess
import os
import configparser
import time

class MyService(win32serviceutil.ServiceFramework):
    _svc_name_ = "RS Faces Service"
    _svc_display_name_ = "RS Faces Service"
    _svc_description_ = "AI Faces service for ResourceSpace"

    def __init__(self, args):
        super().__init__(args)
        self.stop_event = win32event.CreateEvent(None, 0, 0, None)
        self.stop_requested = False
        self.process = None  # Track the subprocess

    def SvcStop(self):
        self.ReportServiceStatus(win32service.SERVICE_STOP_PENDING)
        self.stop_requested = True

        if self.process and self.process.poll() is None:
            try:
                self.process.terminate()
                self.process.wait(timeout=5)
            except Exception:
                try:
                    self.process.kill()
                except Exception:
                    pass  # Optionally log the error
        win32event.SetEvent(self.stop_event)


    def SvcDoRun(self):
        self.main()

    def main(self):
        script_dir = os.path.dirname(os.path.abspath(__file__))
        config_path = os.path.join(script_dir, "faces_config.ini")

        config = configparser.ConfigParser()
        config.read(config_path)

        svc_section = "Faces Service"
        db_section = "Database"

        if svc_section not in config:
            raise ValueError(f"Missing section [{svc_section}] in config file")
        if db_section not in config:
            raise ValueError(f"Missing section [{db_section}] in config file")

        venv_python = config.get(svc_section, "venv_python")
        script_path = config.get(svc_section, "script_path")
        enable_logging = config.getboolean(svc_section, "enable_logging", fallback=False)
        port = config.get(svc_section, "port")
    
        dbhost = config.get(db_section, "db-host")
        dbuser = config.get(db_section, "db-user")
        dbpass = config.get(db_section, "db-pass")
        args = ["--db-host", dbhost, "--db-user", dbuser, "--db-pass", dbpass, "--port", port]

        creationflags = subprocess.CREATE_NEW_PROCESS_GROUP
        log_file = os.path.join(script_dir, "faces_service.log") if enable_logging else os.devnull

        with open(log_file, "a", encoding="utf-8") as log:
            try:
                self.process = subprocess.Popen(
                    [venv_python, script_path] + args,
                    stdout=log,
                    stderr=log,
                    creationflags=creationflags
                )

                # Wait for process to exit or service stop signal
                while self.process.poll() is None and not self.stop_requested:
                    time.sleep(0.5)

            except Exception as ex:
                log.write(f"Service failed to start subprocess: {str(ex)}\n")

if __name__ == '__main__':
    win32serviceutil.HandleCommandLine(MyService)
