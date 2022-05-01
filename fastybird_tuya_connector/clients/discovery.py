#!/usr/bin/python3

#     Copyright 2021. FastyBird s.r.o.
#
#     Licensed under the Apache License, Version 2.0 (the "License");
#     you may not use this file except in compliance with the License.
#     You may obtain a copy of the License at
#
#         http://www.apache.org/licenses/LICENSE-2.0
#
#     Unless required by applicable law or agreed to in writing, software
#     distributed under the License is distributed on an "AS IS" BASIS,
#     WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
#     See the License for the specific language governing permissions and
#     limitations under the License.

"""
Tuya connector clients module discovery client
"""

# Python base dependencies
import logging
import socket
from hashlib import md5
from typing import Optional, Union

# Library dependencies
from Crypto.Cipher import AES

# Library libs
from fastybird_tuya_connector.clients.client import IClient
from fastybird_tuya_connector.logger import Logger
from fastybird_tuya_connector.types import ClientType


class DiscoveryClient(IClient):  # pylint: disable=too-many-instance-attributes
    """
    Tuya devices discovery client

    @package        FastyBird:TuyaConnector!
    @module         clients/discovery

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __connection: Optional[socket.socket] = None

    __logger: Union[Logger, logging.Logger]

    __BIND_IP: str = "0.0.0.0"
    __UDP_PORT: int = 6667

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
    ) -> None:
        self.__logger = logger

    # -----------------------------------------------------------------------------

    @property
    def type(self) -> ClientType:
        """Client type"""
        return ClientType.DISCOVERY

    # -----------------------------------------------------------------------------

    def start(self) -> None:
        """Start communication"""
        self.__create_client()

    # -----------------------------------------------------------------------------

    def stop(self) -> None:
        """Stop communication"""
        if self.__connection is not None:
            try:
                self.__connection.shutdown(socket.SHUT_RDWR)
                self.__connection = None

            except socket.error:
                pass

    # -----------------------------------------------------------------------------

    def is_connected(self) -> bool:
        """Check if client is connected"""
        return self.__connection is not None

    # -----------------------------------------------------------------------------

    def handle(self) -> None:
        """Tuya devices discovery handle"""
        if self.__connection is None:
            return

        try:
            data_tmp, address = self.__connection.recvfrom(4048)

            print(address)
            print(
                self.__unpad(AES.new(md5(b"yGAdlopoPVldABfn").digest(), AES.MODE_ECB).decrypt(data_tmp[20:-8])).decode()
            )

        except Exception as ex:  # pylint: disable=broad-except
            self.__logger.error(
                "Error receiving UDP",
                extra={
                    "exception": {
                        "message": str(ex),
                        "code": type(ex).__name__,
                    },
                },
            )
            self.__logger.exception(ex)

    # -----------------------------------------------------------------------------

    def __create_client(self) -> None:
        """Create CoAP socket client"""
        if self.__connection is None:
            try:
                self.__connection = socket.socket(socket.AF_INET, socket.SOCK_DGRAM, socket.IPPROTO_UDP)

                self.__connection.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)
                self.__connection.bind((self.__BIND_IP, self.__UDP_PORT))
                self.__connection.settimeout(3)

            except Exception as ex:  # pylint: disable=broad-except
                self.__logger.error(
                    "UDP client can't be created",
                    extra={
                        "exception": {
                            "message": str(ex),
                            "code": type(ex).__name__,
                        },
                    },
                )
                self.__logger.exception(ex)

    # -----------------------------------------------------------------------------

    @staticmethod
    def __unpad(string: bytes) -> bytes:
        return string[: -ord(string[len(string) - 1 :])]
