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
Tuya connector types module
"""

# Python base dependencies
from enum import unique

# Library dependencies
from fastybird_metadata.enum import ExtendedEnum

CONNECTOR_NAME: str = "tuya"
DEVICE_NAME: str = "tuya"


@unique
class ClientType(ExtendedEnum):
    """
    Connector client type

    @package        FastyBird:TuyaConnector!
    @module         types

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    DEVICE: str = "device"
    DISCOVERY: str = "discovery"

    # -----------------------------------------------------------------------------

    def __hash__(self) -> int:
        return hash(self._name_)  # pylint: disable=no-member


@unique
class DeviceProtocolVersion(ExtendedEnum):
    """
    Device communication protocol version

    @package        FastyBird:TuyaConnector!
    @module         types

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    V31: str = "3.1"
    V33: str = "3.3"

    # -----------------------------------------------------------------------------

    def __hash__(self) -> int:
        return hash(self._name_)  # pylint: disable=no-member


@unique
class DeviceStatusType(ExtendedEnum):
    """
    Device status data event type

    @package        FastyBird:TuyaConnector!
    @module         types

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    STATUS: str = "status"
    COMMAND: str = "command"

    # -----------------------------------------------------------------------------

    def __hash__(self) -> int:
        return hash(self._name_)  # pylint: disable=no-member
