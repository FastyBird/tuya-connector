import socket
from hashlib import md5
from Crypto.Cipher import AES
from bitstring import BitArray
from tuyaface import aescipher
from tuyaface.const import CMD_TYPE
from fastybird_tuya_connector.protocol.local import TuyaLocalProtocol
from fastybird_tuya_connector.types import DeviceProtocolVersion

def main():
    print("Main")

    try:
        connection = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        connection.setsockopt(socket.IPPROTO_TCP, socket.TCP_NODELAY, 1)
        #connection.settimeout(0.5)
        connection.connect(('10.10.10.101', 6668))

    except Exception as ex:  # pylint: disable=broad-except
        print("Create socket error")

        return

    while True:
        try:
            data, _ = connection.recvfrom(4048)
            print(data)

            break

        except socket.timeout:
            print("Timeout")

            break

    print("Shut down")

    connection.close()

def test():
    print("BUILD")

    protocol = TuyaLocalProtocol(
        device_identifier="402675772462ab280dff",
        device_key="712aadb9520c1dc2",
        gateway_identifier="402675772462ab280dff",
        protocol_version=DeviceProtocolVersion.V31,
    )

    payload = protocol.build_payload(1, CMD_TYPE.CONTROL, {1: True})

    cnt = 0
    for byte in payload:
        print("{0} - {1}".format(cnt, byte))
        cnt = cnt + 1

# main()
test()
