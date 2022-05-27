import ssl
from typing import Optional, Dict

from websockets.legacy.client import WebSocketClientProtocol


def connect(
    uri: str,
    extra_headers: Optional[Dict] = None,
    ssl: Optional[ssl.SSLContext] = None,
    ping_interval: Optional[float] = None,
    ping_timeout: Optional[float] = None,
) -> WebSocketClientProtocol: ...
