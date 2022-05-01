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
Tuya connector clients module device client
"""

# Python base dependencies
import binascii
import json
import logging
import select
import socket
import time
from hashlib import md5
from typing import Callable, Dict, Generator, List, Optional, Tuple, Union

# Library dependencies
from bitstring import BitArray
from tuyaface import aescipher
from tuyaface.const import CMD_TYPE
from tuyaface.helper import hex2bytes

# Library libs
from fastybird_tuya_connector.clients.client import IClient
from fastybird_tuya_connector.logger import Logger
from fastybird_tuya_connector.types import (
    ClientType,
    DeviceProtocolVersion,
    DeviceStatusType,
)


class DeviceClient(IClient):  # pylint: disable=too-many-instance-attributes
    """
    Tuya device client

    @package        FastyBird:TuyaConnector!
    @module         clients/device

    @author         Adam Kadlec <adam.kadlec@fastybird.com>
    """

    __device_identifier: str
    __gateway_identifier: str
    __local_key: str
    __ip_address: str
    __protocol_version: DeviceProtocolVersion

    __force_reconnect: bool = False
    __last_msg_rcv: float
    __last_reconnect: float = 0.0

    __sequence_nr: int = 0
    __connection: Optional[socket.socket] = None

    __on_connection: Optional[Callable[[bool], None]] = None
    __on_status: Optional[Callable[[Dict, DeviceStatusType], None]] = None

    __logger: Union[Logger, logging.Logger]

    __HEART_BEAT_TIME = 7
    __CONNECTION_STALE_TIME = 7
    __RECONNECT_COOL_DOWN_TIME = 5

    # -----------------------------------------------------------------------------

    def __init__(  # pylint: disable=too-many-arguments
        self,
        device_identifier: str,
        local_key: str,
        ip_address: str,
        gateway_identifier: Optional[str] = None,
        protocol_version: DeviceProtocolVersion = DeviceProtocolVersion.V33,
        on_connection: Optional[Callable[[bool], None]] = None,
        on_status: Optional[Callable[[Dict, DeviceStatusType], None]] = None,
        logger: Union[Logger, logging.Logger] = logging.getLogger("dummy"),
    ) -> None:
        super().__init__()

        self.__device_identifier = device_identifier
        self.__gateway_identifier = device_identifier if gateway_identifier is None else gateway_identifier
        self.__local_key = local_key
        self.__ip_address = ip_address
        self.__protocol_version = protocol_version

        self.__force_reconnect = False
        self.__last_msg_rcv = time.time()
        self.__last_reconnect = 0

        self.__on_connection = on_connection
        self.__on_status = on_status

        self.__logger = logger

    # -----------------------------------------------------------------------------

    @property
    def type(self) -> ClientType:
        """Client type"""
        return ClientType.DEVICE

    # -----------------------------------------------------------------------------

    def start(self) -> None:
        """Start client communication"""
        if self.__connection is None:
            try:
                self.__logger.debug("(%s) connecting", self.__ip_address)
                self.__connect()
                self.__logger.info("(%s) connected", self.__ip_address)

            except socket.error:
                self.__logger.error("(%s) exception when opening socket", self.__ip_address)

    # -----------------------------------------------------------------------------

    def stop(self) -> None:
        """Stop client communication"""
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

    def handle(self) -> None:  # pylint: disable=too-many-statements,too-many-branches
        """Tuya device handle"""
        try:  # pylint: disable=too-many-nested-blocks
            if self.__force_reconnect:
                self.__force_reconnect = False

                self.__logger.warning("(%s) reconnecting", self.__ip_address)

                now = time.time()

                if now - self.__last_reconnect < self.__RECONNECT_COOL_DOWN_TIME:
                    self.__logger.debug("(%s) waiting before reconnecting", self.__ip_address)

                    return

                self.__last_reconnect = time.time()

                if self.__connection is not None:
                    try:
                        self.__connection.close()

                    except socket.error:
                        self.__logger.error("(%s) exception when closing socket", self.__ip_address)

                    if self.__on_connection is not None:
                        self.__on_connection(False)

                    self.__connection = None

                    return

            if self.__connection is None:
                try:
                    self.__logger.debug("(%s) connecting", self.__ip_address)
                    self.__connect()
                    self.__logger.info("(%s) connected", self.__ip_address)

                    return

                except socket.error:
                    self.__logger.error("(%s) exception when opening socket", self.__ip_address)

            if self.__connection is not None:
                try:
                    r_list, _, _ = select.select([self.__connection], [], [], self.__CONNECTION_STALE_TIME / 2)

                    for ready in r_list:
                        if isinstance(ready, socket.socket):
                            try:
                                data = self.__connection.recv(4096)
                                # self.__logger.debug(
                                #     "(%s) read from socket '%s' (%s)",
                                #     self.__ip_address,
                                #     "".join(format(x, "02x") for x in data), len(data),
                                # )

                                if data:
                                    for reply in self.__process_raw_reply(data):
                                        self.__last_msg_rcv = time.time()

                                        if reply["cmd"] == CMD_TYPE.HEART_BEAT:
                                            self.__pong()

                                        if (
                                            self.__on_status is not None
                                            and reply["cmd"] == CMD_TYPE.STATUS
                                            and reply["data"]
                                        ):
                                            self.__on_status(json.loads(reply["data"]), DeviceStatusType.STATUS)

                                else:
                                    self.__force_reconnect = True

                            except socket.error:
                                self.__logger.error("(%s) exception when reading from socket", self.__ip_address)

                                self.__force_reconnect = True

                except ValueError:
                    self.__logger.error("(%s) exception when waiting for socket", self.__ip_address)

                    self.__force_reconnect = True

                if self.__is_connection_stale():
                    self.__logger.debug("(%s) connection stale", self.__ip_address)

                    self.__force_reconnect = True

        except Exception as ex:  # pylint: disable=broad-except
            self.__logger.error(
                "(%s) Unexpected exception",
                self.__ip_address,
                extra={
                    "exception": {
                        "message": str(ex),
                        "code": type(ex).__name__,
                    },
                },
            )
            self.__logger.exception(ex)

    # -----------------------------------------------------------------------------

    def read_states(self) -> Optional[Dict[str, Union[str, int, Dict[str, Union[int, float, bool, str]]]]]:
        """Get device status"""
        if self.__connection is None:
            try:
                self.__connect()

            except socket.error:
                self.__logger.debug("(%s) exception when connecting to device", self.__ip_address)

                return None

        try:
            status_reply, all_replies = self.__read_from_device(command=CMD_TYPE.DP_QUERY)

            if len(all_replies) > 0:
                self.__last_msg_rcv = time.time()

            heartbeat = self.__select_command_reply(replies=all_replies, command=CMD_TYPE.HEART_BEAT)

            if heartbeat is not None:
                self.__pong()

            if status_reply is None:
                status_reply = {"data": "{}"}

            json_reply = json.loads(str(status_reply["data"]))

            if self.__on_status is not None:
                self.__on_status(json_reply, DeviceStatusType.STATUS)

            return json_reply  # type: ignore[no-any-return]

        except socket.error as ex:
            self.__logger.debug("(%s) exception when reading status", self.__ip_address)
            self.__logger.exception(ex)

        return None

    # -----------------------------------------------------------------------------

    def write_states(self, value: Dict[Union[str, int], Union[int, float, bool, str]]) -> bool:
        """Set device status"""
        if self.__connection is None:
            try:
                self.__connect()

            except socket.error:
                self.__logger.debug("(%s) exception when connecting to device", self.__ip_address)

                return False

        try:
            state_reply, all_replies = self.__write_to_device(dps=value)

            if all_replies:
                self.__last_msg_rcv = time.time()

            for reply in all_replies:
                if reply["cmd"] == CMD_TYPE.HEART_BEAT:
                    self.__pong()

                if self.__on_status is not None and reply["cmd"] == CMD_TYPE.STATUS and reply["data"]:
                    json_reply = json.loads(str(reply["data"]))

                    self.__on_status(json_reply, DeviceStatusType.COMMAND)

            if not state_reply or ("rc" in state_reply and state_reply["rc"] != 0):
                return False

            return True

        except socket.error:
            self.__logger.debug("(%s) exception when writing status", self.__ip_address)

        return False

    # -----------------------------------------------------------------------------

    def write_state(self, value: Union[int, float, bool, str], idx: int = 1) -> bool:
        """Set state"""
        return self.write_states({idx: value})

    # -----------------------------------------------------------------------------

    def __is_connection_stale(self) -> bool:
        """Indicate if connection has expired"""
        if (time.time() - self.__last_msg_rcv) > self.__HEART_BEAT_TIME:
            self.__ping()

        return (time.time() - self.__last_msg_rcv) > self.__HEART_BEAT_TIME + self.__CONNECTION_STALE_TIME

    # -----------------------------------------------------------------------------

    def __ping(self) -> None:
        """Send a ping message"""
        try:
            self.__logger.debug("(%s) PING", self.__ip_address)

            self.__send_request(command=CMD_TYPE.HEART_BEAT)

        except socket.error:
            self.__logger.debug("(%s) exception when sending heartbeat", self.__ip_address)

            self.__force_reconnect = True

    # -----------------------------------------------------------------------------

    def __pong(self) -> None:
        """Handle received pong message"""
        self.__logger.debug("(%s) PONG", self.__ip_address)

    # -----------------------------------------------------------------------------

    def __connect(self, timeout: int = 2) -> None:
        """Connect to the Tuya device"""
        if self.__connection is not None:
            return

        try:
            self.__logger.info("(%s) Connecting to %s", self.__ip_address, self.__ip_address)

            self.__connection = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

            self.__connection.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
            self.__connection.settimeout(timeout)

            self.__connection.connect((self.__ip_address, 6668))

            if self.__on_connection is not None:
                self.__on_connection(True)

            self.__last_msg_rcv = time.time()
            self.__force_reconnect = False

        except Exception as ex:
            self.__logger.warning("(%s) Failed to connect to %s", self.__ip_address, self.__ip_address)
            self.__logger.exception(ex)

            raise ex

    # -----------------------------------------------------------------------------

    def __read_from_device(
        self,
        command: CMD_TYPE,
        recurse_cnt: int = 0,
    ) -> Tuple[Optional[Dict[str, Union[int, str]]], List[Dict[str, Union[int, str]]]]:
        """Send current status request to the Tuya device and waits for status update"""
        request_cnt = self.__send_request(command=command)

        replies: List[Dict[str, Union[int, str]]] = []
        request_reply: Optional[Dict[str, Union[int, str]]] = None
        status_reply: Optional[Dict[str, Union[int, str]]] = None

        # There might already be data waiting in the socket, e.g. a heartbeat reply, continue reading until
        # the expected response has been received or there is a timeout
        # If status is triggered by DP_QUERY, the status is in a DP_QUERY message
        # If status is triggered by CONTROL_NEW, the status is a STATUS message
        while request_reply is None or (command == CMD_TYPE.CONTROL_NEW and status_reply is None):
            received_replies = list(reply for reply in self.__receive_replies(max_receive_cnt=1))

            replies = replies + received_replies

            request_reply = self.__select_command_reply(replies=replies, command=command, seq=request_cnt)
            status_reply = self.__select_status_reply(replies=replies)

            if len(received_replies) == 0:
                break

        # If there is valid reply to CMD_TYPE.DP_QUERY, use it as status reply
        if (
            command == CMD_TYPE.DP_QUERY
            and request_reply is not None
            and "data" in request_reply
            and request_reply["data"] != "json obj data unvalid"
        ):
            status_reply = request_reply

        if not status_reply and recurse_cnt < 3:
            if request_reply and request_reply["data"] == "json obj data unvalid":
                # Some devices (ie LSC Bulbs) only offer partial status with CONTROL_NEW instead of DP_QUERY
                status_reply, new_replies = self.__read_from_device(
                    command=CMD_TYPE.CONTROL_NEW,
                    recurse_cnt=(recurse_cnt + 1),
                )

            else:
                status_reply, new_replies = self.__read_from_device(
                    command=command,
                    recurse_cnt=(recurse_cnt + 1),
                )

            replies = replies + new_replies

        return status_reply, replies

    # -----------------------------------------------------------------------------

    def __write_to_device(
        self,
        dps: Dict[Union[str, int], Union[int, float, bool, str]],
    ) -> Tuple[Optional[Dict[str, Union[int, str]]], List[Dict[str, Union[int, str]]]]:
        """Send state update request to the Tuya device and waits response"""
        request_cnt = self.__send_request(command=CMD_TYPE.CONTROL, payload={str(k): v for k, v in dps.items()})

        replies: List[Dict[str, Union[int, str]]] = []
        request_reply: Optional[Dict[str, Union[int, str]]] = None

        # There might already be data waiting in the socket, e.g. a heartbeat reply, continue reading until
        # the expected response has been received or there is a timeout
        while request_reply is None:
            received_replies = list(reply for reply in self.__receive_replies(max_receive_cnt=1))

            replies = replies + received_replies

            request_reply = self.__select_command_reply(replies=replies, command=CMD_TYPE.CONTROL, seq=request_cnt)

            if len(received_replies) == 0:
                break

        return request_reply, replies

    # -----------------------------------------------------------------------------

    def __send_request(
        self,
        command: CMD_TYPE = CMD_TYPE.DP_QUERY,
        payload: Optional[Dict[Union[str, int], Union[int, float, bool, str]]] = None,
    ) -> int:
        """Connect to the Tuya device and send a request"""
        if self.__connection is None:
            self.__connect()

        if self.__connection is None:
            raise Exception("Connection to device couldn't be established")

        self.__sequence_nr = self.__sequence_nr + 1

        request = self.__generate_payload(command=command, data=payload)

        self.__logger.debug(
            "(%s) sending msg (seq %s): [%x:%s] payload: [%s]",
            self.__ip_address,
            self.__sequence_nr,
            command,
            CMD_TYPE(command).name,
            payload,
        )
        # self.__logger.debug(
        #     "(%s) write to socket: '%s'",
        #     self.__ip_address,
        #     "".join(format(x, "02x") for x in request),
        # )

        try:
            self.__connection.send(request)

        except Exception as ex:
            raise ex

        return self.__sequence_nr

    # -----------------------------------------------------------------------------

    def __generate_payload(
        self,
        command: CMD_TYPE,
        data: Optional[Dict[Union[str, int], Union[int, float, bool, str]]] = None,
    ) -> bytes:
        """
        Generate the payload to send

        Args:
            command: The type of command
                This is one of the entries from payload_dict
            data: The data to be sent
                This is what will be passed via the 'dps' entry
        """
        payload_json = self.__generate_json_data(command=command, data=data).replace(" ", "").encode("utf-8")

        header_payload_hb: bytes = b""

        payload_hb = payload_json

        if self.__protocol_version == DeviceProtocolVersion.V31:
            if command == CMD_TYPE.CONTROL:
                payload_crypt = aescipher.encrypt(self.__local_key, payload_json)

                pre_md5_string = b"data=" + payload_crypt + b"||lpv=" + b"3.1||" + self.__local_key.encode()

                md5_hash = md5()
                md5_hash.update(pre_md5_string)

                hex_digest = md5_hash.hexdigest()

                header_payload_hb = b"3.1" + hex_digest[8:][:16].encode("latin1")

                payload_hb = header_payload_hb + payload_crypt

            return self.__stitch_payload(payload=payload_hb, command=command)

        if self.__protocol_version == DeviceProtocolVersion.V33:
            if command != CMD_TYPE.DP_QUERY:
                header_payload_hb = b"3.3" + b"\0\0\0\0\0\0\0\0\0\0\0\0"

            payload_crypt = aescipher.encrypt(self.__local_key, payload_json, False)

            return self.__stitch_payload(payload=(header_payload_hb + payload_crypt), command=command)

        raise Exception(f"Unknown protocol {self.__protocol_version.value}")

    # -----------------------------------------------------------------------------

    def __generate_json_data(
        self,
        command: CMD_TYPE,
        data: Optional[Dict[Union[str, int], Union[int, float, bool, str]]] = None,
    ) -> str:
        """Fill the data structure for the command with the given values"""
        payload_dict: Dict[int, Dict] = {
            CMD_TYPE.CONTROL: {"devId": "", "uid": "", "t": ""},
            CMD_TYPE.STATUS: {"gwId": "", "devId": ""},
            CMD_TYPE.HEART_BEAT: {},
            CMD_TYPE.DP_QUERY: {"gwId": "", "devId": "", "uid": "", "t": ""},
            CMD_TYPE.CONTROL_NEW: {"devId": "", "uid": "", "t": ""},
            CMD_TYPE.DP_QUERY_NEW: {"devId": "", "uid": "", "t": ""},
        }

        json_data: Dict[str, Union[str, Dict[Union[str, int], Union[int, float, bool, str]]]] = payload_dict.get(
            command, {}
        )

        if "gwId" in json_data:
            json_data["gwId"] = self.__gateway_identifier

        if "devId" in json_data:
            json_data["devId"] = self.__device_identifier

        if "uid" in json_data:
            json_data["uid"] = self.__device_identifier  # still use id, no separate uid

        if "t" in json_data:
            json_data["t"] = str(int(time.time()))

        if command == CMD_TYPE.CONTROL_NEW:
            json_data["dps"] = {"1": "", "2": "", "3": ""}

        if data is not None:
            json_data["dps"] = data

        return json.dumps(json_data)

    # -----------------------------------------------------------------------------

    def __stitch_payload(self, payload: bytes, command: CMD_TYPE) -> bytes:
        """Join the payload request parts together."""
        command_hb = command.to_bytes(4, byteorder="big")
        request_cnt_hb = self.__sequence_nr.to_bytes(4, byteorder="big")

        payload_hb = payload + hex2bytes("000000000000aa55")

        payload_hb_len_hs = len(payload_hb).to_bytes(4, byteorder="big")

        header_hb = hex2bytes("000055aa") + request_cnt_hb + command_hb + payload_hb_len_hs
        buffer_hb = header_hb + payload_hb

        # calc the CRC of everything except where the CRC goes and the suffix
        hex_crc = format(binascii.crc32(buffer_hb[:-8]) & 0xFFFFFFFF, "08X")

        return buffer_hb[:-8] + hex2bytes(hex_crc) + buffer_hb[-4:]

    # -----------------------------------------------------------------------------

    def __receive_replies(self, max_receive_cnt: int) -> Generator:
        if max_receive_cnt <= 0:
            return

        if self.__connection is None:
            return

        try:
            data = self.__connection.recv(4096)
            # self.__logger.debug(
            #     "(%s) read from socket: '%s'",
            #     self.__ip_address, "".join(format(x, '02x') for x in data),
            # )

            for reply in self.__process_raw_reply(data):
                yield reply

        except socket.timeout:
            pass

        except Exception as ex:
            raise ex

        yield from self.__receive_replies(max_receive_cnt=(max_receive_cnt - 1))

    # -----------------------------------------------------------------------------

    def __process_raw_reply(self, raw_reply: bytes) -> Generator:
        """Split the raw reply(s) into chunks and decrypts it"""
        for splitted in BitArray(raw_reply).split("0x000055aa", bytealigned=True):
            s_bytes = splitted.tobytes()
            payload = None

            # Skip invalid messages
            if len(s_bytes) < 28 or not splitted.endswith("0x0000aa55"):
                continue

            # Parse header
            seq = int.from_bytes(s_bytes[4:8], byteorder="big")
            cmd = int.from_bytes(s_bytes[8:12], byteorder="big")
            size = int.from_bytes(s_bytes[12:16], byteorder="big")
            return_code = int.from_bytes(s_bytes[16:20], byteorder="big")
            has_return_code = (return_code & 0xFFFFFF00) == 0
            crc = int.from_bytes(s_bytes[-8:-4], byteorder="big")

            # Check CRC
            if crc != binascii.crc32(s_bytes[:-8]):
                continue

            if self.__protocol_version == DeviceProtocolVersion.V31:
                data = s_bytes[20:-8]

                if s_bytes[20:21] == b"{":
                    if not isinstance(data, str):
                        payload = data.decode()

                elif s_bytes[20:23] == b"3.1":
                    self.__logger.info("we've got a 3.1 reply, code untested")

                    data = data[3:]  # remove version header

                    # remove (what I'm guessing, but not confirmed is) 16-bytes of MD5 hex digest of payload
                    data = data[16:]
                    payload = aescipher.decrypt(self.__local_key, data)

            elif self.__protocol_version == DeviceProtocolVersion.V33:
                if size > 12:
                    data = s_bytes[20 : 8 + size]

                    if cmd == CMD_TYPE.STATUS:
                        data = data[15:]

                    payload = aescipher.decrypt(self.__local_key, data, False)

            msg = {"cmd": cmd, "seq": seq, "data": payload}

            if has_return_code:
                msg["rc"] = return_code

            self.__logger.debug(
                "(%s) received msg (seq %s): [%x:%s] rc: [%s] payload: [%s]",
                self.__ip_address,
                msg["seq"],
                msg["cmd"],
                CMD_TYPE(int(str(msg["cmd"]))).name,
                return_code if has_return_code else "-",
                msg.get("data", ""),
            )

            yield msg

    # -----------------------------------------------------------------------------

    def __select_command_reply(
        self,
        replies: List[Dict[str, Union[int, str]]],
        command: CMD_TYPE,
        seq: Optional[int] = None,
    ) -> Optional[Dict[str, Union[int, str]]]:
        """Find a valid command reply"""
        filtered_replies = list(filter(lambda x: x["cmd"] == command, replies))

        if seq is not None:
            filtered_replies = list(filter(lambda x: x["seq"] == seq, filtered_replies))

        if len(filtered_replies) == 0:
            return None

        if len(filtered_replies) > 1:
            self.__logger.info(
                "Got multiple replies %s for request [%x:%s]",
                filtered_replies,
                command,
                CMD_TYPE(command).name,
            )

        return filtered_replies[0]

    # -----------------------------------------------------------------------------

    @staticmethod
    def __select_status_reply(
        replies: List[Dict[str, Union[int, str]]],
    ) -> Optional[Dict[str, Union[int, str]]]:
        """Find the first valid status reply"""
        filtered_replies = list(filter(lambda x: x["data"] and x["cmd"] == CMD_TYPE.STATUS, replies))

        if len(filtered_replies) == 0:
            return None

        return filtered_replies[0]
