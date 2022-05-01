from typing import Optional, Generator, Union, Any


class Bits(object): ...

class BitArray(Bits):
    def __init__(
        self,
        auto: Union[str, bytes, None] = None,
        length: Optional[int] = None,
        offset: Optional[int] = None,
        **kwargs: Any,
    ) -> None: ...

    def split(
        self,
        delimiter: str,
        start: Optional[int] = None,
        end: Optional[int] = None,
        count: Optional[int] = None,
        bytealigned: Optional[bool] = None,
    ) -> Generator: ...