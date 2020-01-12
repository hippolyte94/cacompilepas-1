import React, {ChangeEvent, ReactNode} from 'react';
import styled from 'styled-components';
import '../../css/DropBox.css';
import {ReactComponent as DropBoxLogo} from "../../img/usable-image-icon.svg";
import {pdfjs, Document, Page} from 'react-pdf';

const Heading = styled.p<{ active: boolean }>`
  color: #5757e7;
  text-align: center;
`;

interface DropBoxBackgroundProps {
    label: string,
    className: string,
    dragged: boolean,
}
// 192.168.43.91

class DropBoxBackground extends React.Component<DropBoxBackgroundProps, any> {
    public render(): ReactNode {
        return (
            <div
                style={{
                    backgroundColor: this.props.dragged ? 'transparent' : '#ffffff'
                }}
                className={(this.props.dragged ? 'pt-3 mt-0 ' : 'pt-4 mt-3 mt-lg-4 mt-md-4 mt-sm-4 pb-4 ') + 'rounded pb-lg-2 pb-md-2 pb-sm-2 ' + this.props.className}
            >
                {this.props.children}
                <div className={'d-none d-lg-block d-md-block d-sm-block'}>
                    <Heading active={false}>
                        {this.props.label}
                    </Heading>
                </div>
            </div>
        );
    }
}

interface DropBoxProps {
    id: string,
    className: string,
    labelNotDragged: string,
    labelDragged: string,
    accept: string,
    backgroundClassName: string,
    handleFileDrop: (event: React.DragEvent<HTMLDivElement>) => void,
    handleFileChange: (event: ChangeEvent<HTMLInputElement>) => void,
}

interface DropBoxState {
    draggingState: string,
    dragged: boolean,
    label: string,
    src: string,
    file: File | null,
}

class DropBox extends React.Component<DropBoxProps, DropBoxState> {
    constructor(props: any) {
        super(props);
        this.state = {
            draggingState: '',
            dragged: false,
            label: this.props.labelNotDragged,
            src: '',
            file: null,
        }
        this.handleDragOver = this.handleDragOver.bind(this);
        this.handleFileDrop = this.handleFileDrop.bind(this);
        this.handleDragEnter = this.handleDragEnter.bind(this);
        this.handleDragExit = this.handleDragExit.bind(this);
        this.handleFileChange = this.handleFileChange.bind(this);
        this.updateFilePreview = this.updateFilePreview.bind(this);
        this.onDocumentLoadSuccess = this.onDocumentLoadSuccess.bind(this);
        this.renderLogo = this.renderLogo.bind(this);
    }

    public handleDragOver(event: React.DragEvent<HTMLDivElement>): void {
        event.preventDefault();
    }

    public handleFileDrop(event: React.DragEvent<HTMLDivElement>): void {
        this.props.handleFileDrop(event);
        this.setState({dragged: true});
        this.setState({label: this.props.labelDragged});
        this.setState({draggingState: 'not dragging'});
        this.updateFilePreview(event.dataTransfer.files[0]);
    }

    public handleDragEnter(event: React.DragEvent<HTMLDivElement>): void {
        this.setState({label: this.props.labelNotDragged});
        this.setState({draggingState: 'dragging'});
    }

    public handleDragExit(event: React.DragEvent<HTMLDivElement>): void {
        if (this.state.dragged) {
            this.setState({label: this.props.labelDragged});
        } else {
            this.setState({label: this.props.labelNotDragged});
        }
        this.setState({draggingState: 'not dragging'});
    }

    public handleFileChange(event: ChangeEvent<HTMLInputElement>): void {
        this.props.handleFileChange(event);
        this.setState({dragged: true});
        this.setState({label: this.props.labelDragged});
        this.setState({draggingState: 'not dragging'});
        // @ts-ignore
        this.updateFilePreview(event.target.files[0]);
    }

    public updateFilePreview(file: File): void {
        let reader = new FileReader();
        this.setState({file: file});

        if (file.type === 'application/pdf') {
            pdfjs.GlobalWorkerOptions.workerSrc = `//cdnjs.cloudflare.com/ajax/libs/pdf.js/${pdfjs.version}/pdf.worker.js`;
        } else {
            reader.addEventListener('load', () => {
                // @ts-ignore
                this.setState({src: reader.result});
            });

            if (file) {
                reader.readAsDataURL(file);
            }
        }
    }

    public onDocumentLoadSuccess(): void {
    }

    public renderLogo(): ReactNode {
        if (this.state.dragged) {
            // @ts-ignore
            if (this.state.file.type.includes('image')) {
                return (
                    <img
                        id={'dragged-logo'}
                        src={this.state.src}
                        alt={'Logo'}
                        className={'dragged-logo'}
                    />
                );
            } else {
                return (
                    <div className={'pdf-container'}>
                        <Document
                            file={this.state.file}
                            onLoadSuccess={this.onDocumentLoadSuccess}
                            noData={<h4>Glisse un fichier</h4>}
                        >
                            <Page height={100} scale={1} pageNumber={1}/>
                        </Document>
                    </div>
                );
            }
        } else {
            return (
                <div
                    className={'rounded-1 not-dragged-logo'}
                >
                    <DropBoxLogo/>
                </div>
            );
        }
    }

    public render(): ReactNode {
        return (
            <div
                className={this.props.className}
                onDragOver={this.handleDragOver}
                onDrop={this.handleFileDrop}
                onDragEnter={this.handleDragEnter}
                onDragExit={this.handleDragExit}
                style={{
                    opacity: this.state.draggingState === 'dragging' ? 0.5 : 1,
                    transform: this.state.draggingState === 'dragging' ? 'rotate(-2deg) translateY(-10px)' : 'rotate(0)',
                }}
            >
                <label className={'file-upload'}>
                    <DropBoxBackground
                        label={this.state.label}
                        className={this.props.backgroundClassName}
                        dragged={this.state.dragged}
                    >
                        <input
                            type={'file'}
                            id={this.props.id}
                            onChange={this.handleFileChange}
                            hidden
                            accept={this.props.accept}/>
                        {this.renderLogo()}
                    </DropBoxBackground>
                </label>
            </div>);
    }
}

export default DropBox;