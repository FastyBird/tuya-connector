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
Tuya connector module
"""

# Python base dependencies
import asyncio
import logging
import uuid
from typing import Dict, Optional, Union

# Library dependencies
from fastybird_devices_module.connectors.connector import IConnector
from fastybird_devices_module.entities.channel import (
    ChannelControlEntity,
    ChannelEntity,
    ChannelPropertyEntity,
)
from fastybird_devices_module.entities.connector import ConnectorControlEntity
from fastybird_devices_module.entities.device import (
    DeviceAttributeEntity,
    DeviceControlEntity,
    DevicePropertyEntity,
)
from fastybird_metadata.types import ControlAction
from kink import inject

# Library libs
from fastybird_tuya_connector.clients.device import DeviceClient
from fastybird_tuya_connector.clients.discovery import DiscoveryClient
from fastybird_tuya_connector.entities import TuyaConnectorEntity, TuyaDeviceEntity
from fastybird_tuya_connector.logger import Logger
from fastybird_tuya_connector.types import DeviceStatusType


def on_status(data: dict, status_type: DeviceStatusType) -> None:
    """Device published status"""
    print("STATUS")
    print(status_type)
    print(data)


def on_connection(value: bool) -> None:
    """Connected to device"""
    print("CONNECTION")
    print(value)


@inject(alias=IConnector)
class TuyaConnector(IConnector):  # pylint: disable=too-many-instance-attributes,too-many-public-methods
    """
    Tuya connector

    @package        FastyBird:TuyaConnector!
    @module         connector

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __stopped: bool = False
    __process_discovery: bool = False

    __connector_id: uuid.UUID

    __discovery_client: DiscoveryClient

    __logger: Union[Logger, logging.Logger]

    __device: DeviceClient

    # -----------------------------------------------------------------------------

    @property
    def id(self) -> uuid.UUID:  # pylint: disable=invalid-name
        """Connector identifier"""
        return self.__connector_id

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        connector_id: uuid.UUID,
        discovery_client: DiscoveryClient,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
    ) -> None:
        self.__connector_id = connector_id
        self.__discovery_client = discovery_client

        self.__logger = logger

    # -----------------------------------------------------------------------------

    def initialize(self, connector: TuyaConnectorEntity) -> None:
        """Set connector to initial state"""

    # -----------------------------------------------------------------------------

    def initialize_device(self, device: TuyaDeviceEntity) -> None:
        """Initialize device in connector registry"""

    # -----------------------------------------------------------------------------

    def remove_device(self, device_id: uuid.UUID) -> None:
        """Remove device from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices(self) -> None:
        """Reset devices registry to initial state"""

    # -----------------------------------------------------------------------------

    def initialize_device_property(self, device: TuyaDeviceEntity, device_property: DevicePropertyEntity) -> None:
        """Initialize device property"""

    # -----------------------------------------------------------------------------

    def notify_device_property(self, device: TuyaDeviceEntity, device_property: DevicePropertyEntity) -> None:
        """Notify device property was reported to connector"""

    # -----------------------------------------------------------------------------

    def remove_device_property(self, device: TuyaDeviceEntity, property_id: uuid.UUID) -> None:
        """Remove device property from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_properties(self, device: TuyaDeviceEntity) -> None:
        """Reset devices properties registry to initial state"""

    # -----------------------------------------------------------------------------

    def initialize_device_attribute(self, device: TuyaDeviceEntity, device_attribute: DeviceAttributeEntity) -> None:
        """Initialize device attribute"""

    # -----------------------------------------------------------------------------

    def notify_device_attribute(self, device: TuyaDeviceEntity, device_attribute: DeviceAttributeEntity) -> None:
        """Notify device attribute was reported to connector"""

    # -----------------------------------------------------------------------------

    def remove_device_attribute(self, device: TuyaDeviceEntity, attribute_id: uuid.UUID) -> None:
        """Remove device attribute from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_attributes(self, device: TuyaDeviceEntity) -> None:
        """Reset devices attributes registry to initial state"""

    # -----------------------------------------------------------------------------

    def initialize_device_channel(self, device: TuyaDeviceEntity, channel: ChannelEntity) -> None:
        """Initialize device channel"""

    # -----------------------------------------------------------------------------

    def remove_device_channel(self, device: TuyaDeviceEntity, channel_id: uuid.UUID) -> None:
        """Remove device channel from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_channels(self, device: TuyaDeviceEntity) -> None:
        """Reset devices channels registry to initial state"""

    # -----------------------------------------------------------------------------

    def initialize_device_channel_property(
        self,
        channel: ChannelEntity,
        channel_property: ChannelPropertyEntity,
    ) -> None:
        """Initialize device channel property"""

    # -----------------------------------------------------------------------------

    def notify_device_channel_property(
        self,
        channel: ChannelEntity,
        channel_property: ChannelPropertyEntity,
    ) -> None:
        """Notify device channel property was reported to connector"""

    # -----------------------------------------------------------------------------

    def remove_device_channel_property(self, channel: ChannelEntity, property_id: uuid.UUID) -> None:
        """Remove device channel property from connector registry"""

    # -----------------------------------------------------------------------------

    def reset_devices_channels_properties(self, channel: ChannelEntity) -> None:
        """Reset devices channels properties registry to initial state"""

    # -----------------------------------------------------------------------------

    def start(self) -> None:
        """Start connector services"""
        # self.__discovery_client.start()

        self.__logger.info("Connector has been started")

        self.__stopped = False

        self.__device = DeviceClient(
            device_identifier="402675772462ab280dff",
            local_key="46664614c91aefae",
            ip_address="192.168.1.134",
            on_connection=on_connection,
            on_status=on_status,
            logger=self.__logger,
        )
        # self.__device.start()

        # Register connector coroutine
        asyncio.ensure_future(self.__worker())

    # -----------------------------------------------------------------------------

    def stop(self) -> None:
        """Close all opened connections & stop connector"""
        self.__discovery_client.stop()
        # self.__device.stop_client()

        self.__logger.info("Connector has been stopped")

        self.__stopped = True

    # -----------------------------------------------------------------------------

    def has_unfinished_tasks(self) -> bool:
        """Check if connector has some unfinished task"""
        return False

    # -----------------------------------------------------------------------------

    def write_property(  # pylint: disable=too-many-branches
        self,
        property_item: Union[DevicePropertyEntity, ChannelPropertyEntity],
        data: Dict,
    ) -> None:
        """Write device or channel property value to device"""

    # -----------------------------------------------------------------------------

    def write_control(
        self,
        control_item: Union[ConnectorControlEntity, DeviceControlEntity, ChannelControlEntity],
        data: Optional[Dict],
        action: ControlAction,
    ) -> None:
        """Write connector control action"""

    # -----------------------------------------------------------------------------

    async def __worker(self) -> None:
        """Run connector service"""
        while True:
            if self.__stopped and self.has_unfinished_tasks():
                return

            # Devices discovery is turned on...
            if self.__process_discovery:
                # ...turn on discovery client if not turned on...
                if not self.__discovery_client.is_connected():
                    self.__discovery_client.start()

                # ...and proces devices discovery
                self.__discovery_client.handle()

            else:
                # If discovery is still active...
                if not self.__discovery_client.is_connected():
                    # ...proces discovery shutdown
                    self.__discovery_client.stop()

                self.__device.handle()
                # data = self.__device.read_states()
                # print(data)

            # Be gentle to server
            await asyncio.sleep(0.01)
            # await asyncio.sleep(1)
