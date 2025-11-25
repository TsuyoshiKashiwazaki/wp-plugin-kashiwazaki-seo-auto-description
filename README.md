# Kashiwazaki SEO Auto Description

[![WordPress](https://img.shields.io/badge/WordPress-5.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-7.4%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--2.0--or--later-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Version](https://img.shields.io/badge/Version-1.0.1-orange.svg)](https://github.com/TsuyoshiKashiwazaki/wp-plugin-kashiwazaki-seo-auto-description/releases)

投稿・固定ページ・カスタム投稿・メディアから自動でSEO最適化されたメタディスクリプションを生成するWordPressプラグイン

## 主な機能

- **AI自動生成**: OpenAI GPTモデルによる高品質なメタディスクリプション生成
- **幅広い対応**: 投稿・固定ページ・カスタム投稿タイプ・メディアファイルに対応
- **柔軟な設定**: 文字数指定（80-500文字）と複数のGPTモデルから選択
- **直感的なUI**: WordPress管理画面に統合されたシンプルなインターフェース
- **ワンクリックコピー**: 生成されたディスクリプションをクリップボードに即座にコピー
- **APIキーテスト**: OpenAI API接続の事前確認機能
- **一括生成**: 複数記事のディスクリプションを一括で生成・登録

## クイックスタート

1. **プラグインのアップロード**
   ```
   wp-content/plugins/ フォルダに本プラグインをアップロード
   ```

2. **プラグインの有効化**
   - WordPress管理画面 > プラグイン > 「Kashiwazaki SEO Auto Description」を有効化

3. **OpenAI APIキーの設定**
   - 管理画面 > Kashiwazaki SEO Auto Description
   - OpenAI APIキーを入力し「APIキーテスト」で動作確認

4. **設定の調整**
   - AIモデルの選択（GPT-4.1 Nano推奨）
   - ディスクリプション文字数の設定（150文字推奨）
   - 対応する投稿タイプの選択

## 使い方

### 基本的な使用方法

1. **投稿・固定ページの編集画面で**:
   - 「Description生成」ボタンをクリック
   - AIが自動でメタディスクリプションを生成
   - 「descriptionをコピー」でクリップボードにコピー

2. **メディアライブラリで**:
   - メディアファイルの編集画面からも同様に生成可能
   - ファイル名、代替テキスト、キャプションを考慮して最適化

3. **カスタム投稿タイプで**:
   - 設定で有効化したカスタム投稿タイプの編集画面で利用可能

4. **一括生成**:
   - 「一括ディスクリプション生成＆登録」メニューから複数記事を選択して一括処理
   - 生成したディスクリプションを抜粋フィールドに一括登録可能

### 高度な設定

- **GPTモデルの選択**: コストと品質のバランスを考慮してモデルを選択
- **文字数の最適化**: SEOに最適な文字数（通常120-160文字）で設定
- **投稿タイプ別の制御**: 必要な投稿タイプのみを有効化

## 技術仕様

### システム要件
- **WordPress**: 5.0以上
- **PHP**: 7.4以上
- **OpenAI API**: 有効なAPIキーが必要

### 対応投稿タイプ
- 投稿 (post)
- 固定ページ (page)
- メディア (attachment)
- カスタム投稿タイプ（設定により選択可能）

### 使用技術
- **AI処理**: OpenAI GPT API
- **フロントエンド**: jQuery, WordPress Admin UI
- **バックエンド**: PHP, WordPress Plugin API
- **通信**: WordPress HTTP API

## 更新履歴

### Version 1.0.1 - 2025-11-25
- 一括ディスクリプション生成＆登録機能を追加
- 抜粋への一括登録機能を追加
- プラグイン一覧に設定リンクを追加

### Version 1.0.0 - 2025-09-10
- 初回リリース
- OpenAI GPTによるメタディスクリプション自動生成機能
- 投稿・固定ページ・メディア・カスタム投稿タイプ対応
- 管理画面での設定機能
- APIキーテスト機能

## ライセンス

GPL-2.0-or-later

## サポート・開発者

**開発者**: 柏崎剛 (Tsuyoshi Kashiwazaki)
**ウェブサイト**: https://www.tsuyoshikashiwazaki.jp/
**サポート**: プラグインに関するご質問や不具合報告は、開発者ウェブサイトまでお問い合わせください。

## 貢献

プラグインの改善にご協力いただける方は、GitHubリポジトリでのIssueやPull Requestをお待ちしています。

## サポート

- [開発者ウェブサイト](https://www.tsuyoshikashiwazaki.jp/)
- お問い合わせは開発者ウェブサイトから
- バグレポートはGitHubのIssueで受付中
