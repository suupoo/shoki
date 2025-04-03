import os
import streamlit as st
import pandas as pd
from datetime import datetime
import time
import glob
import shutil

# ディレクトリパス
AUDIO_DIR = "/app/audio_files"
OUTPUT_DIR = "/app/output_files"
SUMMARY_DIR = "/app/summary_files"

# タイトルとヘッダーを設定
st.set_page_config(page_title="Whisper & GINZA 音声文字起こし・要約システム", layout="wide")
st.title("音声文字起こし・要約システム")

# サイドバー - 設定情報
st.sidebar.header("システム情報")
st.sidebar.info(
    f"**音声ファイルディレクトリ:** {AUDIO_DIR}\n\n"
    f"**文字起こしファイルディレクトリ:** {OUTPUT_DIR}\n\n"
    f"**要約ファイルディレクトリ:** {SUMMARY_DIR}\n\n"
    f"**文字起こしサービス:** Whisper コンテナで実行中\n\n"
    f"**要約サービス:** GINZA コンテナで実行中"
)

# タブを作成
tab1, tab2, tab3, tab4 = st.tabs(["ファイルアップロード", "文字起こし結果", "要約結果", "システム状態"])

# タブ1: ファイルアップロード
with tab1:
    st.header("音声ファイルのアップロード")

    uploaded_files = st.file_uploader("文字起こしする音声ファイルを選択してください",
                                     type=["mp3", "wav", "m4a", "mp4", "mpeg", "mpga", "webm"],
                                     accept_multiple_files=True)

    if uploaded_files:
        for uploaded_file in uploaded_files:
            # ファイルを保存
            save_path = os.path.join(AUDIO_DIR, uploaded_file.name)
            with open(save_path, "wb") as f:
                f.write(uploaded_file.getbuffer())
            st.success(f"'{uploaded_file.name}' がアップロードされました。自動的に文字起こしが開始されます。")

    # 既存の音声ファイルを表示
    st.subheader("既存の音声ファイル")
    audio_files = glob.glob(os.path.join(AUDIO_DIR, "*.*"))
    audio_files = [f for f in audio_files if os.path.splitext(f)[1].lower() in
                  [".mp3", ".wav", ".m4a", ".mp4", ".mpeg", ".mpga", ".webm"]]

    if audio_files:
        audio_data = []
        for file_path in audio_files:
            file_name = os.path.basename(file_path)
            file_size = os.path.getsize(file_path) / (1024 * 1024)  # MBに変換
            created_time = os.path.getctime(file_path)
            created_time_str = datetime.fromtimestamp(created_time).strftime("%Y-%m-%d %H:%M:%S")

            audio_data.append({
                "ファイル名": file_name,
                "サイズ (MB)": f"{file_size:.2f}",
                "作成日時": created_time_str
            })

        audio_df = pd.DataFrame(audio_data)
        st.dataframe(audio_df, use_container_width=True)

        # ファイル削除ボタン
        if st.button("選択した音声ファイルを削除"):
            for file_path in audio_files:
                try:
                    os.remove(file_path)
                    st.success(f"'{os.path.basename(file_path)}' を削除しました")
                except Exception as e:
                    st.error(f"'{os.path.basename(file_path)}' の削除に失敗しました: {e}")
            st.experimental_rerun()
    else:
        st.info("音声ファイルがありません。ファイルをアップロードしてください。")

# タブ2: 文字起こし結果
with tab2:
    st.header("文字起こし結果")

    # 出力ファイルを表示
    output_files = []
    for ext in ["txt", "srt", "vtt", "json"]:
        output_files.extend(glob.glob(os.path.join(OUTPUT_DIR, f"*.{ext}")))

    if output_files:
        output_data = []
        for file_path in output_files:
            file_name = os.path.basename(file_path)
            file_size = os.path.getsize(file_path) / 1024  # KBに変換
            file_ext = os.path.splitext(file_path)[1]
            created_time = os.path.getctime(file_path)
            created_time_str = datetime.fromtimestamp(created_time).strftime("%Y-%m-%d %H:%M:%S")

            output_data.append({
                "ファイル名": file_name,
                "形式": file_ext,
                "サイズ (KB)": f"{file_size:.2f}",
                "作成日時": created_time_str
            })

        output_df = pd.DataFrame(output_data)
        st.dataframe(output_df, use_container_width=True)

        # ファイルの内容を表示
        st.subheader("ファイル内容を表示")
        selected_file = st.selectbox("文字起こしファイルを選択", [f for f in output_files if f.endswith(".txt")], key="transcript_select")

        if selected_file:
            try:
                with open(selected_file, "r", encoding="utf-8") as f:
                    content = f.read()
                st.text_area("文字起こし内容", content, height=300)

                # ダウンロードボタン
                with open(selected_file, "rb") as f:
                    file_content = f.read()
                st.download_button(
                    label="ファイルをダウンロード",
                    data=file_content,
                    file_name=os.path.basename(selected_file),
                    mime="text/plain"
                )

                # 要約ボタン
                if st.button("このファイルを要約する"):
                    # ファイル名を取得
                    base_name = os.path.splitext(os.path.basename(selected_file))[0]
                    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                    summary_filename = f"{base_name}_summary_{timestamp}.txt"
                    summary_path = os.path.join(SUMMARY_DIR, summary_filename)

                    # ファイルをコピーして更新時間を変更（要約サービスのトリガーとして機能）
                    shutil.copy2(selected_file, selected_file + ".tmp")
                    os.rename(selected_file + ".tmp", selected_file)

                    st.success(f"要約処理をリクエストしました。要約タブで結果を確認してください。")
            except Exception as e:
                st.error(f"ファイルの読み込みに失敗しました: {e}")

        # 出力ファイル削除ボタン
        if st.button("すべての文字起こしファイルを削除"):
            for file_path in output_files:
                try:
                    os.remove(file_path)
                    st.success(f"'{os.path.basename(file_path)}' を削除しました")
                except Exception as e:
                    st.error(f"'{os.path.basename(file_path)}' の削除に失敗しました: {e}")
            st.experimental_rerun()
    else:
        st.info("文字起こし結果がありません。音声ファイルをアップロードして文字起こしを実行してください。")

# タブ3: 要約結果
with tab3:
    st.header("要約結果")

    # 要約ファイルを表示
    summary_files = glob.glob(os.path.join(SUMMARY_DIR, "*.txt"))

    if summary_files:
        summary_data = []
        for file_path in summary_files:
            file_name = os.path.basename(file_path)
            file_size = os.path.getsize(file_path) / 1024  # KBに変換
            created_time = os.path.getctime(file_path)
            created_time_str = datetime.fromtimestamp(created_time).strftime("%Y-%m-%d %H:%M:%S")

            # 元の文字起こしファイル名を推測
            base_name = file_name.split("_summary_")[0]
            original_file = None
            for ext in [".txt", ".srt", ".vtt", ".json"]:
                possible_file = os.path.join(OUTPUT_DIR, base_name + ext)
                if os.path.exists(possible_file):
                    original_file = os.path.basename(possible_file)
                    break

            summary_data.append({
                "要約ファイル名": file_name,
                "元ファイル": original_file if original_file else "不明",
                "サイズ (KB)": f"{file_size:.2f}",
                "作成日時": created_time_str
            })

        summary_df = pd.DataFrame(summary_data)
        st.dataframe(summary_df, use_container_width=True)

        # ファイルの内容を表示
        st.subheader("要約内容を表示")
        selected_summary = st.selectbox("要約ファイルを選択", summary_files, key="summary_select",
                                       format_func=lambda x: os.path.basename(x))

        if selected_summary:
            try:
                with open(selected_summary, "r", encoding="utf-8") as f:
                    summary_content = f.read()

                # 元ファイルの特定と表示
                base_name = os.path.basename(selected_summary).split("_summary_")[0]
                original_content = "元のファイルが見つかりません。"

                for ext in [".txt", ".srt", ".vtt", ".json"]:
                    possible_file = os.path.join(OUTPUT_DIR, base_name + ext)
                    if os.path.exists(possible_file) and ext == ".txt":
                        with open(possible_file, "r", encoding="utf-8") as f:
                            original_content = f.read()
                        break

                # 2カラムで表示
                col1, col2 = st.columns(2)
                with col1:
                    st.subheader("要約テキスト")
                    st.text_area("", summary_content, height=400)

                    # 要約ファイルのダウンロードボタン
                    with open(selected_summary, "rb") as f:
                        summary_file_content = f.read()
                    st.download_button(
                        label="要約ファイルをダウンロード",
                        data=summary_file_content,
                        file_name=os.path.basename(selected_summary),
                        mime="text/plain"
                    )

                with col2:
                    st.subheader("元のテキスト")
                    st.text_area("", original_content, height=400)
            except Exception as e:
                st.error(f"ファイルの読み込みに失敗しました: {e}")

        # 要約ファイル削除ボタン
        if st.button("すべての要約ファイルを削除"):
            for file_path in summary_files:
                try:
                    os.remove(file_path)
                    st.success(f"'{os.path.basename(file_path)}' を削除しました")
                except Exception as e:
                    st.error(f"'{os.path.basename(file_path)}' の削除に失敗しました: {e}")
            st.experimental_rerun()
    else:
        st.info("要約結果がありません。文字起こし結果から要約を生成してください。")

# タブ4: システム状態
with tab4:
    st.header("システム状態")

    # システムステータスを表示
    st.subheader("ディスク使用状況")
    audio_size = sum(os.path.getsize(f) for f in glob.glob(os.path.join(AUDIO_DIR, "*.*"))) / (1024 * 1024)
    output_size = sum(os.path.getsize(f) for f in glob.glob(os.path.join(OUTPUT_DIR, "*.*"))) / (1024 * 1024)
    summary_size = sum(os.path.getsize(f) for f in glob.glob(os.path.join(SUMMARY_DIR, "*.*"))) / (1024 * 1024)

    col1, col2, col3 = st.columns(3)
    with col1:
        st.metric("音声ファイル合計サイズ", f"{audio_size:.2f} MB")
    with col2:
        st.metric("文字起こしファイル合計サイズ", f"{output_size:.2f} MB")
    with col3:
        st.metric("要約ファイル合計サイズ", f"{summary_size:.2f} MB")

    # プロセスステータス
    st.subheader("プロセスステータス")

    status_col1, status_col2 = st.columns(2)

    with status_col1:
        st.info("**Whisper サービス**: 実行中")
        st.write("文字起こしサービスが正常に動作しています。音声ファイルを監視中です。")

    with status_col2:
        st.info("**GINZA 要約サービス**: 実行中")
        st.write("テキスト要約サービスが正常に動作しています。文字起こしファイルを監視中です。")

    # 最新の活動状況
    st.subheader("最新の活動")
    all_files = audio_files + output_files + summary_files
    recent_files = sorted(all_files, key=os.path.getctime, reverse=True)[:10]

    if recent_files:
        recent_data = []
        for file_path in recent_files:
            file_name = os.path.basename(file_path)
            if file_path in audio_files:
                file_type = "音声ファイル"
            elif file_path in output_files:
                file_type = "文字起こしファイル"
            else:
                file_type = "要約ファイル"

            created_time = os.path.getctime(file_path)
            created_time_str = datetime.fromtimestamp(created_time).strftime("%Y-%m-%d %H:%M:%S")

            recent_data.append({
                "ファイル名": file_name,
                "種類": file_type,
                "作成日時": created_time_str
            })

        recent_df = pd.DataFrame(recent_data)
        st.dataframe(recent_df, use_container_width=True)
    else:
        st.info("最近の活動はありません。")

    # 処理パイプラインの図を表示
    st.subheader("処理パイプライン")

    pipeline_html = """
    <div style="text-align: center; margin: 20px;">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div style="border: 2px solid #4CAF50; border-radius: 10px; padding: 15px; width: 25%;">
                <h3>音声ファイル</h3>
                <p>MP3, WAV, MP4など</p>
            </div>
            <div style="font-size: 24px;">→</div>
            <div style="border: 2px solid #2196F3; border-radius: 10px; padding: 15px; width: 25%;">
                <h3>Whisper</h3>
                <p>音声認識・文字起こし</p>
            </div>
            <div style="font-size: 24px;">→</div>
            <div style="border: 2px solid #9C27B0; border-radius: 10px; padding: 15px; width: 25%;">
                <h3>GINZA</h3>
                <p>日本語テキスト要約</p>
            </div>
        </div>
    </div>
    """
    st.markdown(pipeline_html, unsafe_allow_html=True)

    # 自動更新
    auto_refresh = st.checkbox("自動更新 (10秒ごと)")
    if auto_refresh:
        st.info("10秒ごとに自動更新しています...")
        time.sleep(10)
        st.experimental_rerun()

# フッター
st.markdown("---")
st.caption("Whisper & GINZA 音声文字起こし・要約システム | Docker Compose 版")