/*
 * brickd
 * Copyright (C) 2012-2014 Matthias Bolte <matthias@tinkerforge.com>
 *
 * main_windows.c: Brick Daemon starting point for Windows
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */

#include <errno.h>
#include <stdbool.h>
#include <stdio.h>
#include <stdlib.h>
#include <winsock2.h>
#include <windows.h>
#include <dbt.h>
#include <conio.h>

#ifndef BRICKD_WDK_BUILD
	#include <tlhelp32.h>
#else
typedef struct {
	DWORD dwSize;
	DWORD cntUsage;
	DWORD th32ProcessID;
	ULONG_PTR th32DefaultHeapID;
	DWORD th32ModuleID;
	DWORD cntThreads;
	DWORD th32ParentProcessID;
	LONG pcPriClassBase;
	DWORD dwFlags;
	TCHAR szExeFile[MAX_PATH];
} PROCESSENTRY32;

#define TH32CS_SNAPPROCESS 0x00000002

HANDLE WINAPI CreateToolhelp32Snapshot(DWORD dwFlags, DWORD th32ProcessID);
BOOL WINAPI Process32First(HANDLE hSnapshot, PROCESSENTRY32 *lppe);
BOOL WINAPI Process32Next(HANDLE hSnapshot, PROCESSENTRY32 *lppe);
#endif

#include <daemonlib/config.h>
#include <daemonlib/event.h>
#include <daemonlib/log.h>
#include <daemonlib/pipe.h>
#include <daemonlib/threads.h>
#include <daemonlib/utils.h>

#include "hardware.h"
#include "network.h"
#include "service.h"
#include "usb.h"
#include "version.h"

static LogSource _log_source = LOG_SOURCE_INITIALIZER;

// general USB device GUID, applies to all Bricks. for the RED Brick this only
// applies to the composite device itself, but not to its functions
static const GUID GUID_DEVINTERFACE_USB_DEVICE =
{ 0xA5DCBF10L, 0x6530, 0x11D2, { 0x90, 0x1F, 0x00, 0xC0, 0x4F, 0xB9, 0x51, 0xED } };

// Brick device GUID (does not apply to the RED Brick). only set by the
// brick.inf driver, not reported by the Brick itself if used driverless on
// Windows 8. therefore it cannot be used as the only way to detect Bricks
static const GUID GUID_DEVINTERFACE_BRICK_DEVICE =
{ 0x870013DDL, 0xFB1D, 0x4BD7, { 0xA9, 0x6C, 0x1F, 0x0B, 0x7D, 0x31, 0xAF, 0x41 } };

// RED Brick device GUID (only applies to the Brick function). set by the
// red_brick.inf driver and reported by the RED Brick itself if used driverless
// on Windows 8. therefore it can be used as the sole way to detect RED Bricks
static const GUID GUID_DEVINTERFACE_RED_BRICK_DEVICE =
{ 0x9536B3B1L, 0x6077, 0x4A3B, { 0x9B, 0xAC, 0x7C, 0x2C, 0xFA, 0x8A, 0x2B, 0xF3 } };

static char _config_filename[1024];
static bool _run_as_service = true;
static bool _pause_before_exit = false;
static Pipe _notification_pipe;
static HWND _message_pump_hwnd = NULL;
static Thread _message_pump_thread;
static bool _message_pump_running = false;

typedef BOOL (WINAPI *QUERYFULLPROCESSIMAGENAME)(HANDLE, DWORD, LPTSTR, PDWORD);

static int get_process_image_name(PROCESSENTRY32 entry, char *buffer, DWORD length) {
	int rc;
	HANDLE handle = NULL;
	QUERYFULLPROCESSIMAGENAME query_full_process_image_name = NULL;

	handle = OpenProcess(PROCESS_QUERY_LIMITED_INFORMATION, FALSE,
	                     entry.th32ProcessID);

	if (handle == NULL && GetLastError() == ERROR_ACCESS_DENIED) {
		handle = OpenProcess(PROCESS_QUERY_INFORMATION, FALSE,
		                     entry.th32ProcessID);
	}

	if (handle == NULL) {
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		log_warn("Could not open process with ID %u: %s (%d)",
		         (uint32_t)entry.th32ProcessID, get_errno_name(rc), rc);

		return -1;
	}

	query_full_process_image_name =
	  (QUERYFULLPROCESSIMAGENAME)GetProcAddress(GetModuleHandleA("kernel32"),
	                                            "QueryFullProcessImageNameA");

	if (query_full_process_image_name != NULL) {
		if (query_full_process_image_name(handle, 0, buffer, &length) == 0) {
			rc = ERRNO_WINAPI_OFFSET + GetLastError();

			log_warn("Could not get image name of process with ID %u: %s (%d)",
			         (uint32_t)entry.th32ProcessID, get_errno_name(rc), rc);

			return -1;
		}
	} else {
		memcpy(buffer, entry.szExeFile, length);
		buffer[length - 1] = '\0';
	}

	CloseHandle(handle);

	return 0;
}

static bool started_by_explorer(bool log_available) {
	int rc;
	bool result = false;
	PROCESSENTRY32 entry;
	DWORD process_id = GetCurrentProcessId();
	HANDLE handle = CreateToolhelp32Snapshot(TH32CS_SNAPPROCESS, 0);
	char buffer[MAX_PATH];
	size_t length;

	if (handle == INVALID_HANDLE_VALUE) {
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		if (log_available) {
			log_warn("Could not create process list snapshot: %s (%d)",
			         get_errno_name(rc), rc);
		} else {
			fprintf(stderr, "Could not create process list snapshot: %s (%d)\n",
			        get_errno_name(rc), rc);
		}

		return false;
	}

	ZeroMemory(&entry, sizeof(entry));

	entry.dwSize = sizeof(PROCESSENTRY32);

	if (Process32First(handle, &entry)) {
		do {
			if (entry.th32ProcessID == process_id) {
				process_id = entry.th32ParentProcessID;

				if (Process32First(handle, &entry)) {
					do {
						if (entry.th32ProcessID == process_id) {
							if (get_process_image_name(entry, buffer,
							                           sizeof(buffer)) < 0) {
								break;
							}

							if (strcasecmp(buffer, "explorer.exe") == 0) {
								result = true;
							} else {
								length = strlen(buffer);

								if (length > 13 /* = strlen("\\explorer.exe") */ &&
								    (strcasecmp(buffer + length - 13, "\\explorer.exe") == 0 ||
								     strcasecmp(buffer + length - 13, ":explorer.exe") == 0)) {
									result = true;
								}
							}

							break;
						}
					} while (Process32Next(handle, &entry));
				}

				break;
			}
		} while (Process32Next(handle, &entry));
	}

	CloseHandle(handle);

	return result;
}

static void forward_notifications(void *opaque) {
	uint8_t byte;

	(void)opaque;

	if (pipe_read(&_notification_pipe, &byte, sizeof(byte)) < 0) {
		log_error("Could not read from notification pipe: %s (%d)",
		          get_errno_name(errno), errno);

		return;
	}

	usb_rescan();
}

static void handle_device_event(DWORD event_type,
                                DEV_BROADCAST_DEVICEINTERFACE *event_data) {
	bool possibly_brick = false;
	bool definitely_brick = false;
	bool definitely_red_brick = false;
	const char *brick_name_prefix1 = "\\\\?\\USB\\"; // according to libusb: "\\?\" == "\\.\" == "##?#" == "##.#" and "\" == "#"
	const char *brick_name_prefix2 = "VID_16D0&PID_063D"; // according to libusb: "Vid_" == "VID_"
	char guid[64] = "<unknown>";
	char buffer[1024] = "<unknown>";
	int rc;
	char *name;
	uint8_t byte = 0;

	// check event type
	if (event_type != DBT_DEVICEARRIVAL && event_type != DBT_DEVICEREMOVECOMPLETE) {
		return;
	}

	// check class GUID
	if (memcmp(&event_data->dbcc_classguid,
	           &GUID_DEVINTERFACE_USB_DEVICE, sizeof(GUID)) == 0) {
		possibly_brick = true;
	} else if (memcmp(&event_data->dbcc_classguid,
	                  &GUID_DEVINTERFACE_BRICK_DEVICE, sizeof(GUID)) == 0) {
		definitely_brick = true;
	} else if (memcmp(&event_data->dbcc_classguid,
	                  &GUID_DEVINTERFACE_RED_BRICK_DEVICE, sizeof(GUID)) == 0) {
		definitely_red_brick = true;
	} else {
		return;
	}

	// convert name
	if (_run_as_service) {
		if (!WideCharToMultiByte(CP_UTF8, 0, (LPCWSTR)&event_data->dbcc_name[0],
		                         -1, buffer, sizeof(buffer), NULL, NULL)) {
			rc = ERRNO_WINAPI_OFFSET + GetLastError();

			log_error("Could not convert device name: %s (%d)",
			          get_errno_name(rc), rc);

			return;
		}

		name = buffer;
	} else {
		name = event_data->dbcc_name;
	}

	if (possibly_brick) {
		// check if name contains Brick vendor and product ID
		if (strlen(name) > strlen(brick_name_prefix1) &&
		    strncasecmp(name + strlen(brick_name_prefix1), brick_name_prefix2,
		                strlen(brick_name_prefix2)) == 0) {
			definitely_brick = true;
		}
	}

	if (!definitely_brick && !definitely_red_brick) {
		return;
	}

	snprintf(guid, sizeof(guid),
	         "{%08X-%04X-%04X-%02X%02X-%02X%02X%02X%02X%02X%02X}",
	         event_data->dbcc_classguid.Data1,
	         event_data->dbcc_classguid.Data2,
	         event_data->dbcc_classguid.Data3,
	         event_data->dbcc_classguid.Data4[0],
	         event_data->dbcc_classguid.Data4[1],
	         event_data->dbcc_classguid.Data4[2],
	         event_data->dbcc_classguid.Data4[3],
	         event_data->dbcc_classguid.Data4[4],
	         event_data->dbcc_classguid.Data4[5],
	         event_data->dbcc_classguid.Data4[6],
	         event_data->dbcc_classguid.Data4[7]);

	switch (event_type) {
	case DBT_DEVICEARRIVAL:
		log_debug("Received device notification (type: arrival, guid: %s, name: %s)",
		          guid, name);

		if (pipe_write(&_notification_pipe, &byte, sizeof(byte)) < 0) {
			log_error("Could not write to notification pipe: %s (%d)",
			          get_errno_name(errno), errno);
		}

		break;

	case DBT_DEVICEREMOVECOMPLETE:
		log_debug("Received device notification (type: removal, guid: %s, name: %s)",
		          guid, name);

		if (pipe_write(&_notification_pipe, &byte, sizeof(byte)) < 0) {
			log_error("Could not write to notification pipe: %s (%d)",
			          get_errno_name(errno), errno);
		}

		break;
	}
}

static LRESULT CALLBACK message_pump_window_proc(HWND hwnd, UINT msg,
                                                 WPARAM wparam, LPARAM lparam) {
	int rc;

	switch (msg) {
	case WM_USER:
		log_debug("Destroying message pump window");

		if (!DestroyWindow(hwnd)) {
			rc = ERRNO_WINAPI_OFFSET + GetLastError();

			log_warn("Could not destroy message pump window: %s (%d)",
			         get_errno_name(rc), rc);
		}

		return 0;

	case WM_DESTROY:
		log_debug("Posting quit message message loop");

		PostQuitMessage(0);

		return 0;

	case WM_DEVICECHANGE:
		handle_device_event(wparam, (DEV_BROADCAST_DEVICEINTERFACE *)lparam);

		return TRUE;
	}

	return DefWindowProc(hwnd, msg, wparam, lparam);
}

static void message_pump_thread_proc(void *opaque) {
	const char *class_name = "tinkerforge-brick-daemon-message-pump";
	Semaphore *handshake = opaque;
	WNDCLASSEX wc;
	int rc;
	MSG msg;

	log_debug("Started message pump thread");

	ZeroMemory(&wc, sizeof(wc));

	wc.cbSize = sizeof(wc);
	wc.style = CS_HREDRAW | CS_VREDRAW;
	wc.lpfnWndProc = (WNDPROC)message_pump_window_proc;
	wc.cbClsExtra = 0;
	wc.cbWndExtra = 0;
	wc.hInstance = NULL;
	wc.hIcon = NULL;
	wc.hCursor = LoadCursor(NULL, IDC_ARROW);
	wc.hbrBackground = (HBRUSH)COLOR_WINDOW;
	wc.lpszMenuName = NULL;
	wc.lpszClassName = class_name;
	wc.hIconSm = NULL;

	if (RegisterClassEx(&wc) == 0) {
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		log_error("Could not register message pump window class: %s (%d)",
		          get_errno_name(rc), rc);

		goto cleanup;
	}

	_message_pump_hwnd = CreateWindowEx(0, class_name, "brickd message pump",
	                                    0, 0, 0, 0, 0, HWND_MESSAGE,
	                                    NULL, NULL, NULL);

	if (_message_pump_hwnd == NULL) {
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		log_error("Could not create message pump window: %s (%d)",
		          get_errno_name(rc), rc);

		goto cleanup;
	}

	_message_pump_running = true;
	semaphore_release(handshake);

	while (_message_pump_running &&
	       (rc = GetMessage(&msg, _message_pump_hwnd, 0, 0)) != 0) {
		if (rc < 0) {
			rc = ERRNO_WINAPI_OFFSET + GetLastError();

			if (rc == ERRNO_WINAPI_OFFSET + ERROR_INVALID_WINDOW_HANDLE) {
				log_debug("Message pump window seems to be destroyed");

				break;
			}

			log_warn("Could not get window message: %s (%d)",
			         get_errno_name(rc), rc);
		} else {
			TranslateMessage(&msg);
			DispatchMessage(&msg);
		}
	}

	log_debug("Stopped message pump thread");

cleanup:
	if (!_message_pump_running) {
		// need to release the handshake in all cases, otherwise
		// message_pump_start will block forever in semaphore_acquire
		semaphore_release(handshake);
	}

	_message_pump_running = false;
}

static int message_pump_start(void) {
	Semaphore handshake;

	log_debug("Starting message pump thread");

	semaphore_create(&handshake);

	thread_create(&_message_pump_thread, message_pump_thread_proc, &handshake);

	semaphore_acquire(&handshake);
	semaphore_destroy(&handshake);

	if (!_message_pump_running) {
		thread_destroy(&_message_pump_thread);

		log_error("Could not start message pump thread");

		return -1;
	}

	return 0;
}

static void message_pump_stop(void) {
	int rc;

	log_debug("Stopping message pump");

	_message_pump_running = false;

	if (!PostMessage(_message_pump_hwnd, WM_USER, 0, 0)) {
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		log_warn("Could not trigger destruction of message pump window: %s (%d)",
		         get_errno_name(rc), rc);
	} else {
		thread_join(&_message_pump_thread);
	}

	thread_destroy(&_message_pump_thread);
}

static DWORD WINAPI service_control_handler(DWORD control, DWORD event_type,
                                            LPVOID event_data, LPVOID context) {
	(void)event_data;
	(void)context;

	switch (control) {
	case SERVICE_CONTROL_INTERROGATE:
		return NO_ERROR;

	case SERVICE_CONTROL_SHUTDOWN:
	case SERVICE_CONTROL_STOP:
		if (control == SERVICE_CONTROL_SHUTDOWN) {
			log_info("Received shutdown command");
		} else {
			log_info("Received stop command");
		}

		service_set_status(SERVICE_STOP_PENDING, NO_ERROR);

		event_stop();

		return NO_ERROR;

	case SERVICE_CONTROL_DEVICEEVENT:
		handle_device_event(event_type, event_data);

		return NO_ERROR;
	}

	return ERROR_CALL_NOT_IMPLEMENTED;
}

static BOOL WINAPI console_ctrl_handler(DWORD ctrl_type) {
	switch (ctrl_type) {
	case CTRL_C_EVENT:
		log_info("Received CTRL_C_EVENT");

		break;

	case CTRL_CLOSE_EVENT:
		log_info("Received CTRL_CLOSE_EVENT");

		break;

	case CTRL_BREAK_EVENT:
		log_info("Received CTRL_BREAK_EVENT");

		break;

	case CTRL_LOGOFF_EVENT:
		log_info("Received CTRL_LOGOFF_EVENT");

		break;

	case CTRL_SHUTDOWN_EVENT:
		log_info("Received CTRL_SHUTDOWN_EVENT");

		break;

	default:
		log_warn("Received unknown event %u", (uint32_t)ctrl_type);

		return FALSE;
	}

	_pause_before_exit = false;

	event_stop();

	return TRUE;
}

// NOTE: RegisterServiceCtrlHandlerEx (via service_init) and SetServiceStatus
//       (via service_set_status) need to be called in all circumstances if
//       brickd is running as service
static int generic_main(bool log_to_file, const char *debug_filter, bool libusb_debug) {
	int exit_code = EXIT_FAILURE;
	const char *mutex_name = "Global\\Tinkerforge-Brick-Daemon-Single-Instance";
	HANDLE mutex_handle = NULL;
	bool fatal_error = false;
	DWORD service_exit_code = NO_ERROR;
	int rc;
	char filename[1024];
	int i;
	FILE *log_file = NULL;
	WSADATA wsa_data;
	DEV_BROADCAST_DEVICEINTERFACE notification_filter;
	HDEVNOTIFY notification_handle;

	mutex_handle = OpenMutex(SYNCHRONIZE, FALSE, mutex_name);

	if (mutex_handle == NULL) {
		rc = GetLastError();

		if (rc == ERROR_ACCESS_DENIED) {
			rc = service_is_running();

			if (rc < 0) {
				fatal_error = true;
				// FIXME: set service_exit_code

				goto error_mutex;
			} else if (rc) {
				fatal_error = true;
				service_exit_code = ERROR_SERVICE_ALREADY_RUNNING;

				log_error("Could not start as %s, another instance is already running as service",
				          _run_as_service ? "service" : "console application");

				goto error_mutex;
			}
		}

		if (rc != ERROR_FILE_NOT_FOUND) {
			fatal_error = true;
			// FIXME: set service_exit_code
			rc += ERRNO_WINAPI_OFFSET;

			log_error("Could not open single instance mutex: %s (%d)",
			          get_errno_name(rc), rc);

			goto error_mutex;
		}
	}

	if (mutex_handle != NULL) {
		fatal_error = true;
		service_exit_code = ERROR_SERVICE_ALREADY_RUNNING;

		log_error("Could not start as %s, another instance is already running",
		          _run_as_service ? "service" : "console application");

		goto error_mutex;
	}

	mutex_handle = CreateMutex(NULL, FALSE, mutex_name);

	if (mutex_handle == NULL) {
		fatal_error = true;
		// FIXME: set service_exit_code
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		log_error("Could not create single instance mutex: %s (%d)",
		          get_errno_name(rc), rc);

		goto error_mutex;
	}

	if (log_to_file) {
		if (GetModuleFileName(NULL, filename, sizeof(filename)) == 0) {
			rc = ERRNO_WINAPI_OFFSET + GetLastError();

			log_warn("Could not get module file name: %s (%d)",
			         get_errno_name(rc), rc);
		} else {
			i = strlen(filename);

			if (i < 4) {
				log_warn("Module file name '%s' is too short", filename);
			} else {
				filename[i - 3] = '\0';
				string_append(filename, sizeof(filename), "log");

				log_file = fopen(filename, "a+");

				if (log_file == NULL) {
					log_warn("Could not open log file '%s'", filename);
				} else {
					printf("Logging to '%s'\n", filename);

					log_set_file(log_file);
				}
			}
		}
	} else if (_run_as_service) {
		log_set_file(NULL);
	}

	if (!_run_as_service &&
	    !SetConsoleCtrlHandler(console_ctrl_handler, TRUE)) {
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		log_warn("Could not set console control handler: %s (%d)",
		         get_errno_name(rc), rc);
	}

	if (config_has_error()) {
		log_error("Error(s) occurred while reading config file '%s'",
		          _config_filename);

		fatal_error = true;

		goto error_config;
	}

	if (_run_as_service) {
		log_info("Brick Daemon %s started (as service)", VERSION_STRING);
	} else {
		log_info("Brick Daemon %s started", VERSION_STRING);
	}

	if (debug_filter != NULL) {
		log_enable_debug_override(debug_filter);
	}

	if (config_has_warning()) {
		log_warn("Warning(s) in config file '%s', run with --check-config option for details",
		         _config_filename);
	}

	// initialize service status
error_config:
error_mutex:
	if (_run_as_service) {
		if (service_init(service_control_handler) < 0) {
			// FIXME: set service_exit_code
			goto error;
		}

		if (!fatal_error) {
			// service is starting
			service_set_status(SERVICE_START_PENDING, NO_ERROR);
		}
	}

	if (fatal_error) {
		goto error;
	}

	// initialize WinSock2
	if (WSAStartup(MAKEWORD(2, 2), &wsa_data) != 0) {
		// FIXME: set service_exit_code
		rc = ERRNO_WINAPI_OFFSET + WSAGetLastError();

		log_error("Could not initialize Windows Sockets 2.2: %s (%d)",
		          get_errno_name(rc), rc);

		goto error_event;
	}

	if (event_init() < 0) {
		// FIXME: set service_exit_code
		goto error_event;
	}

	if (hardware_init() < 0) {
		// FIXME: set service_exit_code
		goto error_hardware;
	}

	if (usb_init(libusb_debug) < 0) {
		// FIXME: set service_exit_code
		goto error_usb;
	}

	// create notification pipe
	if (pipe_create(&_notification_pipe, 0) < 0) {
		// FIXME: set service_exit_code

		log_error("Could not create notification pipe: %s (%d)",
		          get_errno_name(errno), errno);

		goto error_pipe;
	}

	if (event_add_source(_notification_pipe.read_end, EVENT_SOURCE_TYPE_GENERIC,
	                     EVENT_READ, forward_notifications, NULL) < 0) {
		// FIXME: set service_exit_code
		goto error_pipe_add;
	}

	// register device notification
	ZeroMemory(&notification_filter, sizeof(notification_filter));

	notification_filter.dbcc_size = sizeof(notification_filter);
	notification_filter.dbcc_devicetype = DBT_DEVTYP_DEVICEINTERFACE;

	if (_run_as_service) {
		notification_handle = RegisterDeviceNotification((HANDLE)service_get_status_handle(),
		                                                 &notification_filter,
		                                                 DEVICE_NOTIFY_SERVICE_HANDLE |
		                                                 DEVICE_NOTIFY_ALL_INTERFACE_CLASSES);
	} else {
		if (message_pump_start() < 0) {
			// FIXME: set service_exit_code
			goto error_pipe_add;
		}

		notification_handle = RegisterDeviceNotification(_message_pump_hwnd,
		                                                 &notification_filter,
		                                                 DEVICE_NOTIFY_WINDOW_HANDLE |
		                                                 DEVICE_NOTIFY_ALL_INTERFACE_CLASSES);
	}

	if (notification_handle == NULL) {
		// FIXME: set service_exit_code
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		log_error("Could not register for device notification: %s (%d)",
		          get_errno_name(rc), rc);

		goto error_notification;
	}

	if (network_init() < 0) {
		// FIXME: set service_exit_code
		goto error_network;
	}

	// running
	if (_run_as_service) {
		service_set_status(SERVICE_RUNNING, NO_ERROR);
	}

	if (event_run(network_cleanup_clients_and_zombies) < 0) {
		// FIXME: set service_exit_code
		goto error_run;
	}

	exit_code = EXIT_SUCCESS;

error_run:
	network_exit();

error_network:
	UnregisterDeviceNotification(notification_handle);

error_notification:
	if (!_run_as_service) {
		message_pump_stop();
	}

	event_remove_source(_notification_pipe.read_end, EVENT_SOURCE_TYPE_GENERIC);

error_pipe_add:
	pipe_destroy(&_notification_pipe);

error_pipe:
	usb_exit();

error_usb:
	hardware_exit();

error_hardware:
	event_exit();

error_event:
	log_info("Brick Daemon %s stopped", VERSION_STRING);

error:
	if (!_run_as_service) {
		// unregister the console handler before exiting the log. otherwise a
		// control event might be send to the control handler after the log
		// is not available anymore and the control handler tries to write a
		// log messages triggering a crash. this situation could easily be
		// created by clicking the close button of the command prompt window
		// while the getch call is waiting for the user to press a key.
		SetConsoleCtrlHandler(console_ctrl_handler, FALSE);
	}

	log_exit();

	config_exit();

	if (_run_as_service) {
		// because the service process can be terminated at any time after
		// entering SERVICE_STOPPED state the mutex is closed beforehand,
		// even though this creates a tiny time window in which the service
		// is still running but the mutex is not held anymore
		if (mutex_handle != NULL) {
			CloseHandle(mutex_handle);
		}

		// service is now stopped
		service_set_status(SERVICE_STOPPED, service_exit_code);
	} else {
		if (_pause_before_exit) {
			printf("Press any key to exit...\n");
			getch();
		}

		if (mutex_handle != NULL) {
			CloseHandle(mutex_handle);
		}
	}

	return exit_code;
}

static void WINAPI service_main(DWORD argc, LPTSTR *argv) {
	DWORD i;
	bool log_to_file = false;
	const char *debug_filter = NULL;
	bool libusb_debug = false;

	for (i = 1; i < argc; ++i) {
		if (strcmp(argv[i], "--log-to-file") == 0) {
			log_to_file = true;
		} else if (strcmp(argv[i], "--debug") == 0) {
			if (i + 1 < argc && strncmp(argv[i + 1], "--", 2) != 0) {
				debug_filter = argv[++i];
			} else {
				debug_filter = "";
			}
		} else if (strcmp(argv[i], "--libusb-debug") == 0) {
			libusb_debug = true;
		} else {
			log_warn("Unknown start parameter '%s'", argv[i]);
		}
	}

	generic_main(log_to_file, debug_filter, libusb_debug);
}

static int service_run(bool log_to_file, const char *debug_filter, bool libusb_debug) {
	SERVICE_TABLE_ENTRY service_table[2];
	int rc;

	service_table[0].lpServiceName = service_get_name();
	service_table[0].lpServiceProc = service_main;

	service_table[1].lpServiceName = NULL;
	service_table[1].lpServiceProc = NULL;

	if (!StartServiceCtrlDispatcher(service_table)) {
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		if (rc == ERRNO_WINAPI_OFFSET + ERROR_FAILED_SERVICE_CONTROLLER_CONNECT) {
			if (log_to_file) {
				printf("Could not start as service, starting as console application\n");
			} else {
				log_info("Could not start as service, starting as console application");
			}

			_run_as_service = false;
			_pause_before_exit = started_by_explorer(true);

			return generic_main(log_to_file, debug_filter, libusb_debug);
		} else {
			log_error("Could not start service control dispatcher: %s (%d)",
			          get_errno_name(rc), rc);

			log_exit();

			config_exit();

			return EXIT_FAILURE;
		}
	}

	return EXIT_SUCCESS;
}

static void print_usage(void) {
	printf("Usage:\n"
	       "  brickd [--help|--version|--check-config|--install|--uninstall|--console]\n"
	       "         [--log-to-file] [--debug [<filter>]] [--libusb-debug]\n"
	       "\n"
	       "Options:\n"
	       "  --help              Show this help\n"
	       "  --version           Show version number\n"
	       "  --check-config      Check config file for errors\n"
	       "  --install           Register as a service and start it\n"
	       "  --uninstall         Stop service and unregister it\n"
	       "  --console           Force start as console application\n"
	       "  --log-to-file       Write log messages to file\n"
	       "  --debug [<filter>]  Set log level to debug and apply optional filter\n"
	       "  --libusb-debug      Set libusb log level to debug\n");

	if (started_by_explorer(false)) {
		printf("\nPress any key to exit...\n");
		getch();
	}
}

static void print_version(void) {
	printf("%s\n", VERSION_STRING);

	if (started_by_explorer(false)) {
		printf("\nPress any key to exit...\n");
		getch();
	}
}

// NOTE: generic_main (directly or via service_run) needs to be called in all
//       circumstances if brickd is running as service
int main(int argc, char **argv) {
	int i;
	bool help = false;
	bool version = false;
	bool check_config = false;
	bool install = false;
	bool uninstall = false;
	bool console = false;
	bool log_to_file = false;
	const char *debug_filter = NULL;
	bool libusb_debug = false;
	int rc;

	fixes_init();

	for (i = 1; i < argc; ++i) {
		if (strcmp(argv[i], "--help") == 0) {
			help = true;
		} else if (strcmp(argv[i], "--version") == 0) {
			version = true;
		} else if (strcmp(argv[i], "--check-config") == 0) {
			check_config = true;
		} else if (strcmp(argv[i], "--install") == 0) {
			install = true;
		} else if (strcmp(argv[i], "--uninstall") == 0) {
			uninstall = true;
		} else if (strcmp(argv[i], "--console") == 0) {
			console = true;
		} else if (strcmp(argv[i], "--log-to-file") == 0) {
			log_to_file = true;
		} else if (strcmp(argv[i], "--debug") == 0) {
			if (i + 1 < argc && strncmp(argv[i + 1], "--", 2) != 0) {
				debug_filter = argv[++i];
			} else {
				debug_filter = "";
			}
		} else if (strcmp(argv[i], "--libusb-debug") == 0) {
			libusb_debug = true;
		} else {
			fprintf(stderr, "Unknown option '%s'\n\n", argv[i]);
			print_usage();

			return EXIT_FAILURE;
		}
	}

	if (help) {
		print_usage();

		return EXIT_SUCCESS;
	}

	if (version) {
		print_version();

		return EXIT_SUCCESS;
	}

	if (GetModuleFileName(NULL, _config_filename, sizeof(_config_filename)) == 0) {
		rc = ERRNO_WINAPI_OFFSET + GetLastError();

		fprintf(stderr, "Could not get module file name: %s (%d)\n",
		        get_errno_name(rc), rc);

		return EXIT_FAILURE;
	}

	i = strlen(_config_filename);

	if (i < 4) {
		fprintf(stderr, "Module file name '%s' is too short", _config_filename);

		return EXIT_FAILURE;
	}

	_config_filename[i - 3] = '\0';
	string_append(_config_filename, sizeof(_config_filename), "ini");

	if (check_config) {
		rc = config_check(_config_filename);

		if (started_by_explorer(false)) {
			printf("\nPress any key to exit...\n");
			getch();
		}

		return rc < 0 ? EXIT_FAILURE : EXIT_SUCCESS;
	}

	if (install && uninstall) {
		fprintf(stderr, "Invalid option combination\n");
		print_usage();

		return EXIT_FAILURE;
	}

	if (install) {
		if (service_install(log_to_file, debug_filter) < 0) {
			return EXIT_FAILURE;
		}
	} else if (uninstall) {
		if (service_uninstall() < 0) {
			return EXIT_FAILURE;
		}
	} else {
		printf("Starting...\n");

		config_init(_config_filename);

		log_init();

		if (console) {
			_run_as_service = false;
			_pause_before_exit = started_by_explorer(true);

			return generic_main(log_to_file, debug_filter, libusb_debug);
		} else {
			return service_run(log_to_file, debug_filter, libusb_debug);
		}
	}

	return EXIT_SUCCESS;
}
