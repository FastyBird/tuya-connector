import socket
from hashlib import md5
from Crypto.Cipher import AES


def main():
    print("Main")

    try:
        connection = socket.socket(socket.AF_INET, socket.SOCK_DGRAM, socket.IPPROTO_UDP)

        connection.setsockopt(socket.SOL_SOCKET, socket.SO_BROADCAST, 1)
        connection.bind(("0.0.0.0", 6667))
        connection.settimeout(10)

    except Exception as ex:  # pylint: disable=broad-except
        print("Create socket error")

        return

    while True:
        try:
            data, _ = connection.recvfrom(4048)
            print(data)
            print("")
            # print(data.decode("utf-8"))
            # print("")
            print(data[20:-8])
            print("")
            # print(data[20:-8].decode("utf-8"))

            try:
                decoded = AES.new(md5(b"yGAdlopoPVldABfn").digest(), AES.MODE_ECB).decrypt(data[20:-8])
                print("")
                print(decoded)

                result = decoded[: -ord(decoded[len(decoded) - 1:])]
                print("")
                print(result)

            except Exception:  # pylint: disable=broad-except
                pass

            return

        except socket.timeout:
            print("Timeout")

            return


main()
