from typing import Union


def encrypt(key: str, raw: Union[str, bytes], use_base64: bool = True) -> bytes: ...

def decrypt(key: str, enc: bytes, use_base64: bool = True) -> str: ...