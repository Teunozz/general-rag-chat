"""Security utilities for the RAG application."""

import ipaddress
import re
import socket
from urllib.parse import urlparse


# Blocked hostnames and patterns for SSRF protection
BLOCKED_HOSTNAMES = [
    "localhost",
    "127.0.0.1",
    "0.0.0.0",
    "::1",
    # Docker internal services
    "redis",
    "postgres",
    "qdrant",
    "celery",
    "frontend",
    "backend",
    # Cloud metadata endpoints
    "169.254.169.254",
    "metadata.google.internal",
    "metadata.goog",
]

# Blocked hostname patterns (regex)
BLOCKED_PATTERNS = [
    r"^10\.",  # 10.0.0.0/8
    r"^172\.(1[6-9]|2[0-9]|3[0-1])\.",  # 172.16.0.0/12
    r"^192\.168\.",  # 192.168.0.0/16
    r"\.local$",  # .local domains
    r"\.internal$",  # .internal domains
    r"\.localhost$",  # .localhost domains
]


def is_private_ip(ip_str: str) -> bool:
    """Check if an IP address is private, loopback, or link-local."""
    try:
        ip = ipaddress.ip_address(ip_str)
        return (
            ip.is_private
            or ip.is_loopback
            or ip.is_link_local
            or ip.is_multicast
            or ip.is_reserved
        )
    except ValueError:
        return False


def is_safe_url(url: str) -> tuple[bool, str]:
    """
    Validate that a URL is safe to fetch (not pointing to internal resources).

    Returns:
        Tuple of (is_safe, error_message). If safe, error_message is empty.
    """
    try:
        parsed = urlparse(url)
    except Exception:
        return False, "Invalid URL format"

    # Must be HTTP or HTTPS
    if parsed.scheme not in ("http", "https"):
        return False, f"Invalid URL scheme: {parsed.scheme}. Only http and https are allowed."

    hostname = parsed.hostname
    if not hostname:
        return False, "URL must have a valid hostname"

    hostname_lower = hostname.lower()

    # Check against blocked hostnames
    for blocked in BLOCKED_HOSTNAMES:
        if hostname_lower == blocked.lower() or hostname_lower.endswith(f".{blocked.lower()}"):
            return False, f"Access to {hostname} is not allowed"

    # Check against blocked patterns
    for pattern in BLOCKED_PATTERNS:
        if re.match(pattern, hostname_lower):
            return False, f"Access to {hostname} is not allowed (matches blocked pattern)"

    # Check if hostname is an IP address
    try:
        ip = ipaddress.ip_address(hostname)
        if is_private_ip(hostname):
            return False, f"Access to private/internal IP {hostname} is not allowed"
    except ValueError:
        # Not an IP address, try to resolve it
        try:
            resolved_ips = socket.getaddrinfo(hostname, None)
            for _, _, _, _, addr in resolved_ips:
                ip_str = addr[0]
                if is_private_ip(ip_str):
                    return False, f"Hostname {hostname} resolves to private IP {ip_str}"
        except socket.gaierror:
            # Could not resolve, allow it (will fail during actual fetch)
            pass

    return True, ""


def validate_password_strength(password: str) -> tuple[bool, str]:
    """
    Validate password meets minimum strength requirements.

    Requirements:
    - At least 12 characters
    - At least one uppercase letter
    - At least one lowercase letter
    - At least one digit
    - At least one special character

    Returns:
        Tuple of (is_valid, error_message). If valid, error_message is empty.
    """
    if len(password) < 12:
        return False, "Password must be at least 12 characters long"

    if not re.search(r"[A-Z]", password):
        return False, "Password must contain at least one uppercase letter"

    if not re.search(r"[a-z]", password):
        return False, "Password must contain at least one lowercase letter"

    if not re.search(r"\d", password):
        return False, "Password must contain at least one digit"

    if not re.search(r"[!@#$%^&*(),.?\":{}|<>_\-+=\[\]\\;'/`~]", password):
        return False, "Password must contain at least one special character"

    return True, ""


def sanitize_filename(filename: str) -> str:
    """
    Sanitize a filename to prevent directory traversal and other attacks.

    Removes path separators, null bytes, and other dangerous characters.
    """
    if not filename:
        return "unnamed"

    # Remove null bytes
    filename = filename.replace("\x00", "")

    # Get just the filename (no path)
    filename = filename.split("/")[-1].split("\\")[-1]

    # Remove/replace dangerous characters
    # Keep only alphanumeric, dots, underscores, hyphens
    safe_chars = []
    for char in filename:
        if char.isalnum() or char in "._-":
            safe_chars.append(char)
        else:
            safe_chars.append("_")

    filename = "".join(safe_chars)

    # Prevent hidden files
    while filename.startswith("."):
        filename = filename[1:]

    # Prevent empty filename
    if not filename:
        return "unnamed"

    # Limit length
    if len(filename) > 255:
        name, ext = filename.rsplit(".", 1) if "." in filename else (filename, "")
        if ext:
            filename = name[: 255 - len(ext) - 1] + "." + ext
        else:
            filename = filename[:255]

    return filename
