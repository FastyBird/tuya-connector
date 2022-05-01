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
Tuya connector clients module clients proxy
"""

# Python base dependencies
from abc import ABC, abstractmethod
from typing import List, Set

# Library libs
from fastybird_tuya_connector.types import ClientType


class IClient(ABC):
    """
    Client interface

    @package        FastyBird:TuyaConnector!
    @module         clients/client

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    # -----------------------------------------------------------------------------

    @property
    @abstractmethod
    def type(self) -> ClientType:
        """Client type"""

    # -----------------------------------------------------------------------------

    @abstractmethod
    def start(self) -> None:
        """Start client communication"""

    # -----------------------------------------------------------------------------

    @abstractmethod
    def stop(self) -> None:
        """Stop client communication"""

    # -----------------------------------------------------------------------------

    @abstractmethod
    def is_connected(self) -> bool:
        """Check if client is connected"""

    # -----------------------------------------------------------------------------

    @abstractmethod
    def handle(self) -> None:
        """Process client requests"""


class Client:
    """
    Clients proxy

    @package        FastyBird:TuyaConnector!
    @module         clients/client

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __clients: Set[IClient] = set()

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        clients: List[IClient],
    ) -> None:
        self.__clients = set(clients)

    # -----------------------------------------------------------------------------

    def start(self) -> None:
        """Start clients"""
        for client in self.__clients:
            client.start()

    # -----------------------------------------------------------------------------

    def stop(self) -> None:
        """Stop clients"""
        for client in self.__clients:
            client.stop()

    # -----------------------------------------------------------------------------

    def is_connected(self) -> None:
        """Check if clients are connected"""
        for client in self.__clients:
            client.is_connected()

    # -----------------------------------------------------------------------------

    def handle(self) -> None:
        """Handle clients actions"""
        for client in self.__clients:
            client.handle()
