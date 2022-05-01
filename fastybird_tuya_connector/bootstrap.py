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
Tuya connector DI container
"""

# pylint: disable=no-value-for-parameter

# Python base dependencies
import logging

# Library dependencies
from kink import di

# Library libs
from fastybird_tuya_connector.clients.discovery import DiscoveryClient
from fastybird_tuya_connector.connector import TuyaConnector
from fastybird_tuya_connector.entities import TuyaConnectorEntity
from fastybird_tuya_connector.logger import Logger


def create_connector(
    connector: TuyaConnectorEntity,
    logger: logging.Logger = logging.getLogger("dummy"),
) -> TuyaConnector:
    """Create Tuya connector services"""
    if isinstance(logger, logging.Logger):
        connector_logger = Logger(connector_id=connector.id, logger=logger)

        di[Logger] = connector_logger
        di["tuya-connector_logger"] = di[Logger]

    else:
        connector_logger = logger

    di[DiscoveryClient] = DiscoveryClient(
        logger=logger,
    )
    di["tuya-connector_discovery-client"] = di[DiscoveryClient]

    # Main connector service
    connector_service = TuyaConnector(
        connector_id=connector.id,
        discovery_client=di[DiscoveryClient],
        logger=connector_logger,
    )
    di[TuyaConnector] = connector_service
    di["tuya-connector_connector"] = connector_service

    return connector_service
