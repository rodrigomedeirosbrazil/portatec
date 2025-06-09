import websocket
import json
import time
import random
import signal
import sys
import threading

# Configurações do dispositivo simulado
CHIP_ID = "123123"
APP_KEY = "hidpxulvt4konvhhd9bl"  # Chave do .env
REVERB_PORT = 8888  # Porta do .env

# Variável global para controlar o estado da aplicação
running = True
periodic_thread = None

def signal_handler(sig, frame):
    """Handler para capturar Ctrl+C"""
    global running
    print("\n👋 Recebido sinal de interrupção. Desconectando dispositivo...")
    running = False
    sys.exit(0)

def on_message(ws, message):
    print(f"📨 Mensagem recebida do servidor: {message}")

    try:
        data = json.loads(message)

        # Se recebemos um comando do servidor
        if 'command' in data:
            print(f"🔧 Comando recebido: {data['command']}")

        # Se é uma confirmação de evento
        if data.get('event') == 'pusher:connection_established':
            print("✅ Conexão WebSocket estabelecida com sucesso!")
            subscribe_to_channel(ws)

    except json.JSONDecodeError:
        print(f"⚠️  Mensagem não é JSON válido: {message}")

def on_error(ws, error):
    print(f"❌ Erro na conexão WebSocket: {error}")

def on_close(ws, close_status_code, close_msg):
    print(f"🔌 Conexão WebSocket fechada. Status: {close_status_code}, Mensagem: {close_msg}")

def on_open(ws):
    print(f"🚀 Conectado ao WebSocket Reverb!")
    print(f"📱 Simulando dispositivo com Chip ID: {CHIP_ID}")

def subscribe_to_channel(ws):
    """Inscreve no canal específico do dispositivo"""
    channel_name = f"device-sync.{CHIP_ID}"

    subscribe_payload = {
        "event": "pusher:subscribe",
        "data": {
            "channel": channel_name
        }
    }

    ws.send(json.dumps(subscribe_payload))
    print(f"📡 Inscrito no canal: {channel_name}")

    # Inicia o envio periódico de dados
    send_device_data_periodically(ws)

def send_device_data(ws):
    """Envia dados simulados do dispositivo"""
    if not running:
        return

    channel_name = f"device-sync.{CHIP_ID}"

    # Simula dados de sensores
    device_data = {
        "temperature": round(random.uniform(20.0, 35.0), 2),
        "humidity": round(random.uniform(40.0, 80.0), 2),
        "battery": random.randint(20, 100),
        "signal_strength": random.randint(-80, -30),
        "uptime": int(time.time() * 1000),  # millis
        "free_memory": random.randint(50000, 100000)
    }

    # Payload para enviar dados do dispositivo
    payload = {
        "event": "DeviceSync",
        "channel": channel_name,
        "data": {
            "chip_id": CHIP_ID,
            "data": device_data
        }
    }

    try:
        ws.send(json.dumps(payload))
        print(f"📊 Dados enviados: {device_data}")
    except Exception as e:
        print(f"⚠️  Erro ao enviar dados: {e}")

def send_device_data_periodically(ws):
    """Envia dados do dispositivo a cada 10 segundos"""
    global periodic_thread

    def periodic_send():
        while running:
            try:
                send_device_data(ws)
                # Usa sleep com timeout menor para responder mais rápido ao Ctrl+C
                for _ in range(100):  # 10 segundos divididos em 100 partes de 0.1s
                    if not running:
                        break
                    time.sleep(0.1)
            except Exception as e:
                print(f"⚠️  Erro ao enviar dados: {e}")
                break

    # Executa em thread separada para não bloquear o WebSocket
    periodic_thread = threading.Thread(target=periodic_send, daemon=True)
    periodic_thread.start()

def simulate_device():
    """Simula um dispositivo IoT conectando via WebSocket"""
    global running

    # URL do servidor Reverb (sem SSL para desenvolvimento local)
    ws_url = f"ws://localhost:{REVERB_PORT}/app/{APP_KEY}?protocol=7&client=js&version=7.0.0&flash=false"

    print(f"🔗 Conectando em: {ws_url}")

    websocket.enableTrace(False)  # Desabilita trace para output mais limpo
    ws = websocket.WebSocketApp(
        ws_url,
        on_open=on_open,
        on_message=on_message,
        on_error=on_error,
        on_close=on_close
    )

    # Executa em loop com reconexão automática
    while running:
        try:
            # Usa ping_interval e ping_timeout para melhor controle
            ws.run_forever(ping_interval=30, ping_timeout=10)
            if not running:
                break
        except KeyboardInterrupt:
            print("\n👋 Desconectando dispositivo...")
            running = False
            break
        except Exception as e:
            if running:
                print(f"⚠️  Erro na conexão: {e}")
                print("🔄 Tentando reconectar em 5 segundos...")
                for _ in range(50):  # 5 segundos divididos em 50 partes de 0.1s
                    if not running:
                        break
                    time.sleep(0.1)

    # Garante que o WebSocket seja fechado
    try:
        ws.close()
    except:
        pass

if __name__ == "__main__":
    # Configura o handler para Ctrl+C
    signal.signal(signal.SIGINT, signal_handler)
    signal.signal(signal.SIGTERM, signal_handler)

    print("🤖 Iniciando simulador de dispositivo IoT")
    print("📋 Pressione Ctrl+C para parar")
    print("-" * 50)

    try:
        simulate_device()
    except KeyboardInterrupt:
        print("\n👋 Finalizando aplicação...")
    finally:
        running = False
        print("✅ Aplicação finalizada com sucesso!")
